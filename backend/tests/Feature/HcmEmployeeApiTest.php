<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Policy;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\CompanyUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    /** @var Company */
    private ?Company $company = null;

    private function bearerToken(bool $isAdmin = true): string
    {
        if ($isAdmin) {
            $result = $this->createHcmAdminWithCompany([
                'name' => 'Hcm Admin',
                'email' => 'hcm-admin@example.com',
                'password' => 'StrongPass1',
            ]);
            $this->company = $result['company'];
            return $result['token'];
        }

        // Regular user without HCM admin permissions
        if (! $this->company) {
            $this->company = Company::query()->firstOrCreate(
                ['code' => 'TEST_COMPANY'],
                ['name' => 'Test Company', 'domain' => 'test-company.local']
            );
        }

        $user = User::factory()->create([
            'name' => 'Regular User',
            'email' => 'regular-user-'.time().'@example.com',
        ]);

        // Add user to company but WITHOUT HCM admin permissions
        $companyUserClass = class_exists('App\\Models\\CompanyUser') ? 'App\\Models\\CompanyUser' : null;
        if ($companyUserClass) {
            $companyUserClass::firstOrCreate([
                'user_id' => $user->id,
                'company_id' => $this->company->id,
            ]);
        }

        // Login
        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'companyCode' => $this->company->code,
        ])->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function adminBearerToken(): string
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_id' => $this->company?->id,
                'team' => 'HR',
                'designation' => 'Manager',
                'employment_status' => 'active',
            ],
        );

        return $token;
    }

    private function createCompanyDepartment(string $name, string $code): Department
    {
        return Department::query()->create([
            'company_id' => $this->company?->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function createDesignationForDepartment(Department $department, string $name, string $code): Designation
    {
        return Designation::query()->create([
            'company_id' => $this->company?->id,
            'department_id' => $department->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function createTeamForDepartment(Department $department, string $name): void
    {
        DB::table('teams')->insert([
            'uuid' => (string) Str::uuid(),
            'company_id' => $this->company?->id,
            'department_id' => $department->id,
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validEmployeePayload(array $overrides = []): array
    {
        $department = Department::query()->firstOrCreate(
            ['code' => 'ENG'],
            ['name' => 'Engineering', 'is_active' => true],
        );
        $designation = Designation::query()->firstOrCreate(
            ['code' => 'DEV'],
            ['department_id' => $department->id, 'name' => 'Developer', 'is_active' => true],
        );

        $team = Team::query()->firstOrCreate(
            [
                'company_id' => $this->company?->id,
                'name' => 'Engineering Team',
            ],
            [
                'department_id' => $department->id,
                'is_active' => true,
            ],
        );

        return array_merge([
            'name' => 'Valid Employee',
            'email' => 'valid.employee@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
            'data_disclosure_acknowledged' => true,
            'team' => $team->name,
            'teamId' => $team->id,
            'departmentId' => $department->id,
            'designationId' => $designation->id,
            'employeeType' => 'permanent',
            'employmentStatus' => 'active',
            'phone' => '081234567890',
            'nik' => '3174011708980001',
            'placeOfBirth' => 'Jakarta',
            'dateOfBirth' => '1998-08-17',
            'gender' => 'female',
            'maritalStatus' => 'single',
            'religion' => 'Islam',
            'nationality' => 'Indonesia',
            'address' => null,
            'addressDetail' => 'Jl. Cilandak KKO No. 12, RT 01/RW 05',
            'baseSalary' => 6500000,
            'fixedAllowance' => 500000,
            'salaryType' => 'monthly',
            'contractType' => 'permanent',
            'contractStatus' => 'active',
            'contractStartDate' => '2025-01-01',
            'bankName' => 'BCA',
            'bankAccountNo' => '1234567890',
            'bankAccountHolderName' => 'Valid Employee',
            'emergencyContacts' => [
                ['name' => 'Ibu Valid', 'relationship' => 'Mother', 'phone' => '081234567891'],
            ],
        ], $this->validWilayahSelection(), $overrides);
    }

    /**
     * @return array<string, int>
     */
    private function validWilayahSelection(): array
    {
        $province = WilayahProvince::query()->firstOrCreate(
            ['code' => '31'],
            ['name' => 'DKI Jakarta'],
        );

        $regency = WilayahRegency::query()->firstOrCreate(
            ['code' => '31.74'],
            [
                'province_id' => $province->id,
                'name' => 'Kota Administrasi Jakarta Selatan',
            ],
        );

        $district = WilayahDistrict::query()->firstOrCreate(
            ['code' => '31.74.09'],
            [
                'regency_id' => $regency->id,
                'name' => 'Jagakarsa',
            ],
        );

        $village = WilayahVillage::query()->firstOrCreate(
            ['code' => '31.74.09.1001'],
            [
                'district_id' => $district->id,
                'name' => 'Jagakarsa',
            ],
        );

        return [
            'provinceId' => $province->id,
            'regencyId' => $regency->id,
            'districtId' => $district->id,
            'villageId' => $village->id,
        ];
    }

    public function test_employees_list_requires_auth(): void
    {
        $this->getJson('/v1/hcm/employees')->assertStatus(401);
    }

    public function test_employees_list_returns_summary_and_rows(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'summary' => [
                        'totalEmployees',
                        'activeEmployees',
                        'inactiveEmployees',
                        'probationEmployees',
                        'newJoiners',
                    ],
                ],
            ]);
    }

    public function test_employees_list_includes_phone_on_rows(): void
    {
        $token = $this->adminBearerToken();
        $user = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'Manager',
                'employment_status' => 'active',
                'phone' => '081234567890',
            ],
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees')
            ->assertOk()
            ->assertJsonPath('data.0.phone', '081234567890');
    }

    public function test_employee_create_with_department_and_designation(): void
    {
        $token = $this->adminBearerToken();
        $dept = Department::query()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);
        $designation = Designation::query()->create([
            'department_id' => $dept->id,
            'name' => 'Senior Developer',
            'code' => 'SRDEV',
            'is_active' => true,
        ]);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Org Hire',
                'email' => 'orghire@example.com',
                'departmentId' => $dept->id,
                'designationId' => $designation->id,
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');
        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $id,
            'department_id' => $dept->id,
            'designation_id' => $designation->id,
        ]);

        $employeeRoleId = (int) HcmRole::query()
            ->where('company_id', $this->company->id)
            ->where('code', 'EMPLOYEE')
            ->value('id');

        $this->assertTrue(
            HcmUserRole::query()
                ->where('company_id', $this->company->id)
                ->where('user_id', $id)
                ->where('role_id', $employeeRoleId)
                ->where('status', 'active')
                ->exists()
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$id)
            ->assertOk()
            ->assertJsonPath('data.departmentId', $dept->id)
            ->assertJsonPath('data.designationId', $designation->id)
            ->assertJsonPath('data.designation', 'Senior Developer');
    }

    public function test_employee_create_rejects_designation_department_mismatch(): void
    {
        $token = $this->adminBearerToken();
        $deptA = Department::query()->create(['name' => 'Dept A', 'code' => 'A', 'is_active' => true]);
        $deptB = Department::query()->create(['name' => 'Dept B', 'code' => 'B', 'is_active' => true]);
        $designation = Designation::query()->create([
            'department_id' => $deptA->id,
            'name' => 'Role A',
            'code' => 'RA',
            'is_active' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Bad Combo',
                'email' => 'badcombo@example.com',
                'departmentId' => $deptB->id,
                'designationId' => $designation->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'DESIGNATION_DEPARTMENT_MISMATCH');
    }

    public function test_employee_create_and_update_profile(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'New Hire',
                'email' => 'newhire@example.com',
                'team' => 'Engineering',
                'employmentStatus' => 'probation',
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $id);

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $id,
            'team' => 'Engineering Team',
            'designation' => 'Developer',
            'employment_status' => 'probation',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'employmentStatus' => 'active',
                'phone' => '081234567800',
            ])
            ->assertOk()
            ->assertJsonPath('data.employmentStatus', 'active')
            ->assertJsonPath('data.phone', '6281234567800');
    }

    public function test_employee_create_accepts_plus62_phone_and_normalizes_to_canonical_value(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Phone Plus Employee',
                'email' => 'phone.plus.employee@example.com',
                'phone' => '+628123456789',
                'emergencyContacts' => [
                    ['name' => 'Budi Santoso', 'relationship' => 'Spouse/Parent', 'phone' => '+628123456788'],
                ],
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$id)
            ->assertOk()
            ->assertJsonPath('data.phone', '628123456789')
            ->assertJsonPath('data.emergencyContacts.0.relationship', 'Spouse/Parent')
            ->assertJsonPath('data.emergencyContacts.0.phone', '+628123456788');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $id,
            'phone' => '628123456789',
        ]);

        $profile = EmployeeProfile::query()->where('user_id', $id)->firstOrFail();
        $this->assertSame('3174011708980001', $profile->nik);
    }

    public function test_tenant_admin_cannot_update_employee_outside_active_company(): void
    {
        $token = $this->adminBearerToken();

        $foreignCompany = Company::query()->create([
            'name' => 'Foreign Tenant',
            'code' => 'foreign_tenant',
            'status' => 'active',
        ]);

        $foreignUser = User::query()->create([
            'name' => 'Foreign Employee',
            'email' => 'foreign.employee@example.com',
            'password' => bcrypt('StrongPass1'),
        ]);

        EmployeeProfile::query()->create([
            'user_id' => $foreignUser->id,
            'company_id' => $foreignCompany->id,
            'employment_status' => 'active',
            'contract_type' => 'permanent',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/employees/'.$foreignUser->id, [
            'baseSalary' => 1234567,
        ])->assertStatus(404)
            ->assertJsonPath('error.code', 'EMPLOYEE_NOT_FOUND');
    }

    public function test_employee_profile_can_store_pkwt_contract_fields(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWT Staff',
                'email' => 'pkwtstaff@example.com',
                'team' => 'Operations',
                'employeeType' => 'contract',
                'employmentStatus' => 'active',
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'contractType' => 'contract',
                'contractStartDate' => '2025-05-01',
                'contractEndDate' => '2026-04-30',
                'phone' => '0811111111',
            ])
            ->assertOk()
            ->assertJsonPath('data.contractType', 'contract')
            ->assertJsonPath('data.contractStartDate', '2025-05-01')
            ->assertJsonPath('data.contractEndDate', '2026-04-30');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $id,
            'contract_type' => 'contract',
            'contract_start_date' => '2025-05-01 00:00:00',
            'contract_end_date' => '2026-04-30 00:00:00',
        ]);
    }

    public function test_employee_profile_can_store_personal_identity_fields(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Identity Staff',
                'email' => 'identity.staff@example.com',
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'nik' => '3174011708980001',
                'placeOfBirth' => 'Jakarta',
                'dateOfBirth' => '1998-08-17',
                'gender' => 'female',
                'maritalStatus' => 'single',
                'religion' => 'Islam',
                'nationality' => 'Indonesia',
            ])
            ->assertOk()
            ->assertJsonPath('data.nik', '3174011708980001')
            ->assertJsonPath('data.placeOfBirth', 'Jakarta')
            ->assertJsonPath('data.dateOfBirth', '1998-08-17')
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.maritalStatus', 'single')
            ->assertJsonPath('data.religion', 'Islam')
            ->assertJsonPath('data.nationality', 'Indonesia');

        // Verify data via model (encrypted fields will be decrypted via cast)
        $profile = EmployeeProfile::query()->where('user_id', $id)->firstOrFail();
        $this->assertSame('3174011708980001', $profile->nik);
        $this->assertSame('Jakarta', $profile->place_of_birth);
        $this->assertSame('female', $profile->gender);
        $this->assertSame('single', $profile->marital_status);
        $this->assertSame('Islam', $profile->religion);
        $this->assertSame('Indonesia', $profile->nationality);

        $profile = EmployeeProfile::query()->where('user_id', $id)->firstOrFail();
        $this->assertSame('1998-08-17', optional($profile->date_of_birth)->toDateString());
    }

    public function test_employee_create_rejects_invalid_formats_and_missing_required_fields(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', [
                'name' => 'Strict Validation',
                'email' => 'strict.validation@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
                'nik' => '12345',
                'phone' => '08abc',
                'nationality' => 'Malaysia',
                'baseSalary' => -100,
                'emergencyContacts' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'departmentId',
                'designationId',
                'employeeType',
                'contractStartDate',
                'contractStatus',
                'provinceId',
                'regencyId',
                'districtId',
                'placeOfBirth',
                'dateOfBirth',
                'gender',
                'maritalStatus',
                'religion',
                'bankName',
                'bankAccountNo',
                'bankAccountHolderName',
                'emergencyContacts',
                'nik',
                'phone',
                'nationality',
                'baseSalary',
            ]);
    }

    public function test_employee_create_rejects_duplicate_nik_in_same_company(): void
    {
        $token = $this->adminBearerToken();
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id];

        $first = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'NIK Unique First',
            'email' => 'nik.unique.first@example.com',
            'nik' => '3174011708980017',
        ]));
        $first->assertStatus(201)->assertJsonPath('success', true);

        $second = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'NIK Unique Second',
            'email' => 'nik.unique.second@example.com',
            'nik' => '3174011708980017',
        ]));

        $second->assertStatus(422)
            ->assertJsonValidationErrors(['nik']);
    }

    public function test_employee_contract_rules_require_end_date_only_for_pkwt(): void
    {
        $token = $this->adminBearerToken();

        $missingEndDate = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWT Missing End',
                'email' => 'pkwt.missing.end@example.com',
                'contractType' => 'contract',
                'contractEndDate' => null,
                'employeeType' => 'contract',
            ]));

        $missingEndDate->assertStatus(422)
            ->assertJsonValidationErrors(['contractEndDate']);

        $unexpectedEndDate = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWTT With End',
                'email' => 'pkwtt.with.end@example.com',
                'contractType' => 'permanent',
                'contractEndDate' => '2026-01-31',
            ]));

        $unexpectedEndDate->assertStatus(422)
            ->assertJsonValidationErrors(['contractEndDate']);
    }

    public function test_employee_create_rejects_invalid_background_step_data(): void
    {
        $token = $this->adminBearerToken();
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id];

        // Emergency contact: garbage name (special chars), garbage relationship, bad phone with dashes
        $badContactName = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Contact Name Test',
            'email' => 'bg.contact1@example.com',
            'emergencyContacts' => [
                ['name' => 'ajhsbxkj@#$%^&*123', 'relationship' => 'friend', 'phone' => '08123456789'],
            ],
        ]));
        $badContactName->assertStatus(422)->assertJsonValidationErrors(['emergencyContacts.0.name']);

        // Emergency contact: phone with dashes
        $badPhone = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Contact Phone Test',
            'email' => 'bg.contact2@example.com',
            'emergencyContacts' => [
                ['name' => 'Budi Santoso', 'relationship' => 'Spouse', 'phone' => '----38912732'],
            ],
        ]));
        $badPhone->assertStatus(422)->assertJsonValidationErrors(['emergencyContacts.0.phone']);

        // Education: year out of range (398237492783497)
        $badEduYear = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Education Year Test',
            'email' => 'bg.edu1@example.com',
            'educationItems' => [
                ['institution' => 'Universitas Indonesia', 'degree' => 'S1', 'startYear' => 398237492783497, 'endYear' => null],
            ],
        ]));
        $badEduYear->assertStatus(422)->assertJsonValidationErrors(['educationItems.0.startYear']);

        // Education: institution with random garbage
        $badEduInst = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Education Inst Test',
            'email' => 'bg.edu2@example.com',
            'educationItems' => [
                ['institution' => 'wdihcnxefgiuywegxnri2u3y49238764928348wdihcnxefgiuywegxnri2u3y49238764928348wdihcnxefgiuywegxnri2u3y49238764928348', 'degree' => 'S1', 'startYear' => 2010, 'endYear' => 2014],
            ],
        ]));
        $badEduInst->assertStatus(422)->assertJsonValidationErrors(['educationItems.0.institution']);

        // Experience: future start date
        $badExpDate = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Experience Date Test',
            'email' => 'bg.exp1@example.com',
            'experienceItems' => [
                ['company' => 'PT Contoh', 'position' => 'Engineer', 'startDate' => '2099-01-01', 'endDate' => null],
            ],
        ]));
        $badExpDate->assertStatus(422)->assertJsonValidationErrors(['experienceItems.0.startDate']);

        // Experience: company name too long
        $badExpCompany = $this->withHeaders($headers)->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Experience Company Test',
            'email' => 'bg.exp2@example.com',
            'experienceItems' => [
                ['company' => str_repeat('x', 101), 'position' => 'Engineer', 'startDate' => '2020-01-01', 'endDate' => null],
            ],
        ]));
        $badExpCompany->assertStatus(422)->assertJsonValidationErrors(['experienceItems.0.company']);
    }

    public function test_employee_create_rejects_oversized_personal_text_fields(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Oversized Text Fields',
            'email' => 'oversized.text.fields@example.com',
            'address' => str_repeat('A', 501),
            'addressDetail' => str_repeat('B', 501),
            'bio' => str_repeat('C', 501),
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'address',
                'addressDetail',
                'bio',
            ]);
    }

    public function test_employee_create_rejects_invalid_bank_account_number_and_holder_name_format(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Invalid Bank Fields',
            'email' => 'invalid.bank.fields@example.com',
            'bankAccountNo' => '12345678901234567890123456789012345',
            'bankAccountHolderName' => 'Holder1234',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'bankAccountNo',
                'bankAccountHolderName',
            ]);
    }

    public function test_employee_create_rejects_invalid_name_place_of_birth_and_bpjs_number_format(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Valid123###',
            'email' => 'invalid.personal.regex@example.com',
            'placeOfBirth' => 'J4kart@',
            'bpjsKesehatanNo' => 'ABC123',
            'bpjsKetenagakerjaanNo' => '12-AB-45',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'placeOfBirth',
                'bpjsKesehatanNo',
                'bpjsKetenagakerjaanNo',
            ]);
    }

    public function test_employee_create_can_compose_address_from_wilayah_hierarchy(): void
    {
        $token = $this->adminBearerToken();

        $province = WilayahProvince::query()->create([
            'code' => '31',
            'name' => 'DKI Jakarta',
        ]);
        $regency = WilayahRegency::query()->create([
            'province_id' => $province->id,
            'code' => '31.74',
            'name' => 'Kota Administrasi Jakarta Selatan',
        ]);
        $district = WilayahDistrict::query()->create([
            'regency_id' => $regency->id,
            'code' => '31.74.09',
            'name' => 'Jagakarsa',
        ]);
        $village = WilayahVillage::query()->create([
            'district_id' => $district->id,
            'code' => '31.74.09.1001',
            'name' => 'Jagakarsa',
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Wilayah Address Employee',
                'email' => 'wilayah.address.employee@example.com',
                'address' => null,
                'addressDetail' => 'Blok A2, dekat masjid, RT 02/RW 03',
                'provinceId' => $province->id,
                'regencyId' => $regency->id,
                'districtId' => $district->id,
                'villageId' => $village->id,
            ]));

        $response->assertStatus(201)->assertJsonPath('success', true);
        $userId = (int) $response->json('data.id');

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $userId,
            'province_id' => $province->id,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'address' => 'Jagakarsa, Jagakarsa, Kota Administrasi Jakarta Selatan, DKI Jakarta',
            'address_detail' => 'Blok A2, dekat masjid, RT 02/RW 03',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$userId)
            ->assertOk()
            ->assertJsonPath('data.addressDetail', 'Blok A2, dekat masjid, RT 02/RW 03')
            ->assertJsonPath('data.addressRegion.provinceId', $province->id)
            ->assertJsonPath('data.addressRegion.regencyId', $regency->id)
            ->assertJsonPath('data.addressRegion.districtId', $district->id)
            ->assertJsonPath('data.addressRegion.villageId', $village->id)
            ->assertJsonPath('data.addressRegion.provinceName', 'DKI Jakarta');
    }
    public function test_employees_filter_by_status(): void
    {
        $token = $this->adminBearerToken();

        $u = User::factory()->create(['email' => 'inactive@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $u->id,
            'company_id' => $this->company?->id,
            'employment_status' => 'inactive',
            'team' => 'X',
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees?status=inactive&perPage=50');

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($u->id, $ids);
    }

    public function test_employees_filter_by_team_id_returns_matching_rows(): void
    {
        $token = $this->adminBearerToken();

        $teamA = Team::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Core Support',
            'is_active' => true,
        ]);
        $teamB = Team::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Field Ops',
            'is_active' => true,
        ]);

        $userA = User::factory()->create(['email' => 'team.filter.a@example.com']);
        $userB = User::factory()->create(['email' => 'team.filter.b@example.com']);

        CompanyUser::query()->firstOrCreate([
            'company_id' => $this->company->id,
            'user_id' => $userA->id,
        ]);
        CompanyUser::query()->firstOrCreate([
            'company_id' => $this->company->id,
            'user_id' => $userB->id,
        ]);

        EmployeeProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $userA->id,
            'team_id' => $teamA->id,
            'team' => $teamA->name,
            'employment_status' => 'active',
        ]);
        EmployeeProfile::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $userB->id,
            'team_id' => $teamB->id,
            'team' => $teamB->name,
            'employment_status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->getJson('/v1/hcm/employees?teamId='.$teamA->id.'&perPage=50');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $rows = collect($response->json('data'));
        $ids = $rows->pluck('id')->values()->all();

        $this->assertContains($userA->id, $ids);
        $this->assertNotContains($userB->id, $ids);

        $row = $rows->firstWhere('id', $userA->id);
        $this->assertEquals($teamA->id, data_get($row, 'teamId'));
        $this->assertEquals($teamA->name, data_get($row, 'teamName'));
    }

    public function test_employee_create_rejects_inactive_team_assignment(): void
    {
        $token = $this->adminBearerToken();

        $dept = Department::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'Ops Inactive Team'],
            ['code' => 'OPS-INACTIVE']
        );
        $designation = Designation::query()->firstOrCreate(
            ['department_id' => $dept->id, 'name' => 'Ops Specialist'],
            ['code' => 'OPS-SPEC', 'is_active' => true]
        );
        $inactiveTeam = Team::query()->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Team Inactive',
            'is_active' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Inactive Team Candidate',
            'email' => 'inactive.team.candidate@example.com',
            'departmentId' => $dept->id,
            'designationId' => $designation->id,
            'teamId' => $inactiveTeam->id,
            'team' => $inactiveTeam->name,
        ]));

        $response->assertUnprocessable();
        $this->assertEquals('TEAM_INACTIVE_NOT_ASSIGNABLE', $response->json('error.code'));
    }

    public function test_employee_update_rejects_inactive_team_assignment(): void
    {
        $token = $this->adminBearerToken();

        $dept = Department::query()->firstOrCreate(
            ['company_id' => $this->company->id, 'name' => 'Ops Update Inactive Team'],
            ['code' => 'OPS-UPD-INACTIVE']
        );
        $designation = Designation::query()->firstOrCreate(
            ['department_id' => $dept->id, 'name' => 'Ops Update Specialist'],
            ['code' => 'OPS-UPD-SPEC', 'is_active' => true]
        );
        $inactiveTeam = Team::query()->create([
            'company_id' => $this->company->id,
            'department_id' => $dept->id,
            'name' => 'Ops Team Inactive Update',
            'is_active' => false,
        ]);

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'name' => 'Team Update Candidate',
            'email' => 'team.update.candidate@example.com',
            'departmentId' => $dept->id,
            'designationId' => $designation->id,
            'team' => null,
            'teamId' => null,
        ]));

        $create->assertCreated();
        $userId = (int) $create->json('data.id');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/employees/'.$userId, [
            'teamId' => $inactiveTeam->id,
        ]);

        $response->assertUnprocessable();
        $this->assertEquals('TEAM_INACTIVE_NOT_ASSIGNABLE', $response->json('error.code'));
    }

    public function test_employee_create_rejects_free_text_team_without_team_id(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'email' => 'free-text-team-create@example.com',
            'teamId' => null,
            'team' => 'Legacy Team Manual',
        ]));

        $response->assertUnprocessable();
        $this->assertEquals('TEAM_MASTER_SELECTION_REQUIRED', $response->json('error.code'));
    }

    public function test_employee_update_rejects_free_text_team_without_team_id(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->postJson('/v1/hcm/employees', $this->validEmployeePayload([
            'email' => 'free-text-team-update@example.com',
        ]));

        $create->assertCreated();
        $userId = (int) $create->json('data.id');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $this->company->id,
        ])->putJson('/v1/hcm/employees/'.$userId, [
            'teamId' => null,
            'team' => 'Manual Legacy Name',
        ]);

        $response->assertUnprocessable();
        $this->assertEquals('TEAM_MASTER_SELECTION_REQUIRED', $response->json('error.code'));
    }

    public function test_employee_update_persists_normalized_history_tables(): void
    {
        $token = $this->adminBearerToken();
        $dept = Department::query()->create(['name' => 'Operations', 'code' => 'OPS', 'is_active' => true]);
        $designation = Designation::query()->create([
            'department_id' => $dept->id,
            'name' => 'Ops Lead',
            'code' => 'OPSLEAD',
            'is_active' => true,
        ]);
        $manager = User::factory()->create(['email' => 'manager-hcm@example.com']);

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Normalized Employee',
                'email' => 'normalized.employee@example.com',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'employmentStatus' => 'active',
                'employeeType' => 'contract',
                'startDate' => '2025-01-10',
                'departmentId' => $dept->id,
                'designationId' => $designation->id,
                'managerUserId' => $manager->id,
                'baseSalary' => 7000000,
                'fixedAllowance' => 1250000,
                'salaryType' => 'monthly',
                'contractType' => 'contract',
                'contractStartDate' => '2025-01-10',
                'contractEndDate' => '2026-01-09',
                'bankName' => 'BCA',
                'bankAccountNo' => '1234567890',
                'bankAccountHolderName' => 'Normalized Employee',
                'npwp' => '12.345.678.9-000.000',
                'taxStatus' => 'TK',
                'ptkpStatus' => 'TK0',
                'bpjsKesehatanNo' => '0001234500013',
                'bpjsKetenagakerjaanNo' => '00098765001',
                'emergencyContacts' => [
                    ['name' => 'Ibu Employee', 'phone' => '0811111111', 'relationship' => 'Mother'],
                ],
                'educationItems' => [
                    ['institution' => 'Universitas A', 'degree' => 'S1', 'startYear' => 2016, 'endYear' => 2020],
                ],
                'experienceItems' => [
                    ['company' => 'PT Lama', 'position' => 'Staff', 'startDate' => '2021-01-01', 'endDate' => '2024-12-31'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.employmentStatus', 'active')
            ->assertJsonPath('data.employeeType', 'contract')
            ->assertJsonPath('data.compensation.salaryType', 'monthly')
            ->assertJsonPath('data.contract.contractType', 'contract');

        $profileId = (int) EmployeeProfile::query()->where('user_id', $id)->value('id');
        $this->assertGreaterThan(0, $profileId);

        $this->assertDatabaseHas('employee_employment_history', [
            'employee_id' => $profileId,
            'employment_status' => 'active',
            'employee_type' => 'contract',
        ]);
        $this->assertDatabaseHas('employee_assignments', [
            'employee_id' => $profileId,
            'department_id' => $dept->id,
            'designation_id' => $designation->id,
            'manager_user_id' => $manager->id,
            'is_primary' => 1,
        ]);
        $this->assertDatabaseHas('employee_compensations', [
            'employee_id' => $profileId,
            'salary_type' => 'monthly',
        ]);
        $this->assertDatabaseHas('employee_contracts', [
            'employee_id' => $profileId,
            'contract_type' => 'contract',
        ]);
        $this->assertDatabaseHas('employee_bank_accounts', [
            'employee_id' => $profileId,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
        ]);
        $this->assertDatabaseHas('employee_tax_profiles', [
            'employee_id' => $profileId,
            'ptkp_status' => 'TK0',
        ]);
        $this->assertDatabaseHas('employee_benefits', [
            'employee_id' => $profileId,
            'bpjs_kesehatan_no' => '0001234500013',
        ]);
        $this->assertSame(1, DB::table('employee_emergency_contacts')->where('employee_id', $profileId)->count());
        $this->assertSame(1, DB::table('employee_educations')->where('employee_id', $profileId)->count());
        $this->assertSame(1, DB::table('employee_experiences')->where('employee_id', $profileId)->count());
    }

    public function test_departments_returns_designation_count_key(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/departments')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'designationCount', 'isActive'],
                ],
            ]);
    }

    public function test_departments_forbidden_when_switching_to_unowned_company(): void
    {
        $token = $this->adminBearerToken();

        Company::query()->create([
            'code' => 'tenant_other_org',
            'name' => 'Tenant Other Org',
            'legal_name' => 'Tenant Other Org LLC',
            'status' => 'active',
            'owner_user_id' => null,
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Code' => 'tenant_other_org',
        ])->getJson('/v1/hcm/departments')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_FORBIDDEN');
    }

    public function test_employees_export_supports_xlsx_csv_and_pdf(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Export Employee',
                'email' => 'export.employee@example.com',
            ]))
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/export?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition');
    }

    public function test_departments_designations_and_policies_export_supports_xlsx_and_pdf(): void
    {
        $token = $this->adminBearerToken();

        $department = Department::query()->firstOrCreate(
            ['code' => 'EXP_DEPT'],
            ['name' => 'Export Department', 'is_active' => true],
        );
        Designation::query()->firstOrCreate(
            ['code' => 'EXP_DESIG'],
            ['department_id' => $department->id, 'name' => 'Export Designation', 'is_active' => true],
        );
        Policy::query()->create([
            'name' => 'Export Policy',
            'description' => 'Policy for export endpoint test',
            'department_id' => $department->id,
            'effective_date' => now()->toDateString(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/departments/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/designations/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/policies/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_policies_list_returns_data_with_tenant_context(): void
    {
        $token = $this->adminBearerToken();

        $department = Department::query()->firstOrCreate(
            ['code' => 'POL_DEPT'],
            ['name' => 'Policy Department', 'is_active' => true],
        );

        Policy::query()->create([
            'name' => 'Policy List Record',
            'description' => 'Policy listing regression check',
            'department_id' => $department->id,
            'effective_date' => now()->toDateString(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/policies?page=1&perPage=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.perPage', 20);
    }

    public function test_bulk_template_and_upload_requires_hcm_admin(): void
    {
        $token = $this->bearerToken(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/bulk-template')
            ->assertStatus(403);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "employee_uuid,name,email,password,confirm_password,team,designation,employment_status,base_salary\n,No Admin,noadmin@example.com,StrongPass1,StrongPass1,HR,Staff,active,5000000\n",
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file])
            ->assertStatus(403);
    }

    public function test_bulk_template_download_contains_reference_sheets(): void
    {
        $token = $this->adminBearerToken();
        $department = $this->createCompanyDepartment('People Operations', 'POPS');
        $this->createDesignationForDepartment($department, 'HR Generalist', 'HRGEN');
        $this->createTeamForDepartment($department, 'Talent Acquisition');

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/bulk-template');

        $response->assertOk()->assertHeader('content-disposition');

        $tmpPath = $response->baseResponse->getFile()->getPathname();
        $spreadsheet = IOFactory::load($tmpPath);
        $sheetNames = $spreadsheet->getSheetNames();

        $this->assertContains('employee_bulk_data', $sheetNames);
        $this->assertContains('ref_departments', $sheetNames);
        $this->assertContains('ref_designations', $sheetNames);
        $this->assertContains('ref_teams', $sheetNames);
        $this->assertContains('ref_banks', $sheetNames);
        $this->assertContains('ref_enums', $sheetNames);

        $templateSheet = $spreadsheet->getSheetByName('employee_bulk_data');
        $this->assertSame('department', $templateSheet->getCell('G1')->getValue());
        $this->assertSame('designation', $templateSheet->getCell('H1')->getValue());
        $this->assertSame('=ref_departments!$B$2:$B$2', $templateSheet->getCell('G2')->getDataValidation()->getFormula1());
        $this->assertSame('=ref_designations!$D$2:$D$2', $templateSheet->getCell('H2')->getDataValidation()->getFormula1());

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        @unlink($tmpPath);
    }

    public function test_bulk_template_requires_department_and_designation_masters(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/bulk-template')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'EMPLOYEE_BULK_ORG_SETUP_REQUIRED');
    }

    public function test_hcm_admin_can_bulk_upload_full_employee_data_from_csv(): void
    {
        $token = $this->adminBearerToken();
        $hrDepartment = $this->createCompanyDepartment('Human Resources', 'HR');
        $financeDepartment = $this->createCompanyDepartment('Finance', 'FIN');
        $this->createDesignationForDepartment($hrDepartment, 'Lead Staff', 'LEADSTAFF');
        $this->createDesignationForDepartment($financeDepartment, 'Analyst', 'ANALYST');
        $employee = User::factory()->create(['email' => 'employee1@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'base_salary' => 0,
            'fixed_allowance' => 0,
        ]);
        $employeeUuid = (string) $employee->uuid;

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "employee_uuid,name,email,password,confirm_password,team,designation,employment_status,base_salary,phone,address,bio,bank_name,bank_account_no,bank_ifsc_code,bank_branch\n"
            ."{$employeeUuid},Employee One,employee1@example.com,,,HR,Lead Staff,active,5500000,08123,Jakarta,Senior Staff,BCA,123456,BCA001,Jakarta\n"
            .",Employee Two,employee2@example.com,StrongPass1,StrongPass1,Finance,Analyst,probation,6000000,08234,Bandung,,Mandiri,98765,MDR001,Bandung\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.createdRows', 1)
            ->assertJsonPath('data.updatedRows', 1)
            ->assertJsonPath('data.failedRows', 0);

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'base_salary' => 5500000,
            'fixed_allowance' => 0,
            'team' => 'HR',
            'designation' => 'Lead Staff',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'employee2@example.com',
            'name' => 'Employee Two',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->get('/v1/hcm/employees/bulk-template')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_hcm_admin_can_bulk_upload_using_department_and_designation_names(): void
    {
        $token = $this->adminBearerToken();
        $department = $this->createCompanyDepartment('People Operations', 'POPS');
        $designation = $this->createDesignationForDepartment($department, 'HR Generalist', 'HRGEN');

        $file = UploadedFile::fake()->createWithContent(
            'employees-named-org.csv',
            "employee_uuid,name,email,password,confirm_password,department,designation,employment_status,base_salary\n"
            .",Named Org Employee,named.org@example.com,StrongPass1,StrongPass1,People Operations,HR Generalist,active,5000000\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.createdRows', 1)
            ->assertJsonPath('data.failedRows', 0);

        $this->assertDatabaseHas('employee_profiles', [
            'company_id' => $this->company?->id,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'designation' => 'HR Generalist',
        ]);
    }

    public function test_bulk_upload_is_blocked_when_package_employee_limit_is_full(): void
    {
        $company = Company::query()->create([
            'code' => 'BULK20',
            'name' => 'Bulk Limit Company',
            'legal_name' => 'Bulk Limit Company LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
        $auth = $this->createHcmAdminWithCompany([
            'name' => 'Bulk Limit Admin',
            'email' => 'bulk-limit-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);
        $this->company = $company;

        $package = Package::query()->create([
            'name' => 'Starter 1',
            'code' => 'starter-1',
            'monthly_price' => 10000,
            'yearly_price' => 100000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => null,
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 1,
        ]);
        Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 10000,
        ]);

        $department = $this->createCompanyDepartment('Human Resources', 'HR');
        $this->createDesignationForDepartment($department, 'Staff', 'STAFF');

        $existingUser = User::factory()->create(['email' => 'existing.bulk.limit@example.com']);
        EmployeeProfile::query()->create([
            'company_id' => $company->id,
            'user_id' => $existingUser->id,
            'employment_status' => 'active',
            'base_salary' => 1000000,
            'fixed_allowance' => 0,
        ]);

        $file = UploadedFile::fake()->createWithContent(
            'employees-over-limit.csv',
            "employee_uuid,name,email,password,confirm_password,department,designation,employment_status,base_salary\n"
            .",Over Limit Employee,over.limit@example.com,StrongPass1,StrongPass1,Human Resources,Staff,active,5000000\n",
        );

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$auth['token'],
            'X-Company-Id' => (string) $company->id,
        ])->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'EMPLOYEE_COUNT_EXCEEDED');

        $this->assertDatabaseMissing('users', ['email' => 'over.limit@example.com']);
    }

    public function test_bulk_upload_respects_subscription_activation_after_pending_payment_is_paid(): void
    {
        $company = Company::query()->create([
            'code' => 'BULKPAY1',
            'name' => 'Bulk Pending Payment Company',
            'legal_name' => 'Bulk Pending Payment Company LLC',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ]);
        $tenantAuth = $this->createHcmAdminWithCompany([
            'name' => 'Bulk Pending Admin',
            'email' => 'bulk-pending-admin@example.com',
            'password' => 'StrongPass1',
        ], $company);
        $this->company = $company;

        $package = Package::query()->create([
            'name' => 'Starter Pending',
            'code' => 'starter-pending',
            'monthly_price' => 10000,
            'yearly_price' => 100000,
            'billing_unit' => 'company',
            'status' => 'active',
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'employee_management',
            'feature_name' => 'Employee Management',
            'limit' => null,
        ]);
        PackageFeature::query()->create([
            'package_uuid' => $package->uuid,
            'feature_code' => 'max_employees',
            'feature_name' => 'Maximum Employees',
            'limit' => 2,
        ]);

        $subscription = Subscription::query()->create([
            'company_id' => $company->id,
            'package_uuid' => $package->uuid,
            'plan_code' => $package->code,
            'status' => 'pending_payment',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'billing_cycle' => 'monthly',
            'amount' => 10000,
        ]);
        $invoice = Invoice::query()->create([
            'company_id' => $company->id,
            'subscription_id' => $subscription->id,
            'purchase_transaction_id' => null,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'amount_due' => 10000,
            'notes' => null,
        ]);

        $department = $this->createCompanyDepartment('Human Resources', 'HR');
        $this->createDesignationForDepartment($department, 'Staff', 'STAFF');

        $file = UploadedFile::fake()->createWithContent(
            'employees-after-payment.csv',
            "employee_uuid,name,email,password,confirm_password,department,designation,employment_status,base_salary\n"
            .",Payment Activated Employee,payment.activated@example.com,StrongPass1,StrongPass1,Human Resources,Staff,active,5000000\n",
        );

        $blocked = $this->withHeaders([
            'Authorization' => 'Bearer '.$tenantAuth['token'],
            'X-Company-Id' => (string) $company->id,
        ])->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $blocked->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'EMPLOYEE_COUNT_EXCEEDED');
        $this->assertDatabaseMissing('users', ['email' => 'payment.activated@example.com']);

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Platform Billing Admin',
            'email' => 'platform-billing-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $platformAdmin = User::query()->where('email', 'platform-billing-admin@example.com')->firstOrFail();
        $platformAdmin->forceFill(['is_super_admin' => true])->save();

        $platformLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => 'platform-billing-admin@example.com',
            'password' => 'StrongPass1',
        ])->assertOk();

        $platformToken = (string) $platformLogin->json('data.accessToken');

        $this->withHeader('Authorization', 'Bearer '.$platformToken)
            ->putJson('/v1/saas/invoices/'.$invoice->uuid.'/mark-paid')
            ->assertOk()
            ->assertJsonPath('success', true);

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);

        $allowed = $this->withHeaders([
            'Authorization' => 'Bearer '.$tenantAuth['token'],
            'X-Company-Id' => (string) $company->id,
        ])->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $allowed->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.createdRows', 1)
            ->assertJsonPath('data.failedRows', 0);

        $this->assertDatabaseHas('users', ['email' => 'payment.activated@example.com']);
    }

    public function test_bulk_upload_rejects_invalid_enums_and_rolls_back_all_rows(): void
    {
        $token = $this->adminBearerToken();
        $department = $this->createCompanyDepartment('Human Resources', 'HR');
        $this->createDesignationForDepartment($department, 'Staff', 'STAFF');

        $file = UploadedFile::fake()->createWithContent(
            'employees-invalid.csv',
            "employee_uuid,name,email,password,confirm_password,team,designation,employment_status,base_salary,contract_type,gender,marital_status,bank_name,tax_status\n"
            .",Valid Employee,valid.rollback@example.com,StrongPass1,StrongPass1,HR,Staff,active,5000000,permanent,male,single,BCA,TK0\n"
            .",Broken Employee,broken.rollback@example.com,StrongPass1,StrongPass1,HR,Staff,active,4000000,invalid_contract,robot,complicated,Bank Khayalan,TK9\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'BULK_UPLOAD_VALIDATION_FAILED')
            ->assertJsonPath('data.createdRows', 0)
            ->assertJsonPath('data.updatedRows', 0);

        $this->assertDatabaseMissing('users', ['email' => 'valid.rollback@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'broken.rollback@example.com']);
    }

    public function test_bulk_upload_rejects_conflicting_employee_uuid_and_email_mapping(): void
    {
        $token = $this->adminBearerToken();
        $department = $this->createCompanyDepartment('Human Resources', 'HR');
        $this->createDesignationForDepartment($department, 'Staff', 'STAFF');
        $first = User::factory()->create(['email' => 'bulk.conflict.first@example.com']);
        $second = User::factory()->create(['email' => 'bulk.conflict.second@example.com']);

        EmployeeProfile::query()->create([
            'user_id' => $first->id,
            'employment_status' => 'active',
            'base_salary' => 1000000,
            'fixed_allowance' => 100000,
        ]);
        EmployeeProfile::query()->create([
            'user_id' => $second->id,
            'employment_status' => 'active',
            'base_salary' => 1000000,
            'fixed_allowance' => 100000,
        ]);

        $employeeUuid = (string) $first->uuid;
        $file = UploadedFile::fake()->createWithContent(
            'employees-conflict.csv',
            "employee_uuid,name,email,password,confirm_password,team,designation,employment_status,base_salary\n"
            ."{$employeeUuid},Conflict Row,bulk.conflict.second@example.com,,,HR,Staff,active,5000000\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'BULK_UPLOAD_VALIDATION_FAILED');

        $errors = $upload->json('data.errors') ?? [];
        $this->assertIsArray($errors);
        $this->assertNotEmpty(array_filter($errors, fn ($item) => str_contains((string) $item, 'employee_uuid dan email mengacu ke user yang berbeda')));
    }

    public function test_contract_transition_to_pkwtt_keeps_history(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Contract Transition',
                'email' => 'transition.contract@example.com',
                'employmentStatus' => 'active',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');
        $profileId = (int) EmployeeProfile::query()->where('user_id', $id)->value('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'contractType' => 'contract',
                'contractStartDate' => '2025-01-01',
                'contractEndDate' => '2025-12-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.contract.contractType', 'contract');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'contractType' => 'permanent',
                'contractStartDate' => '2026-01-01',
                'contractEndDate' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.contract.contractType', 'permanent');

        $this->assertSame(2, DB::table('employee_contracts')->where('employee_id', $profileId)->count());
        $this->assertDatabaseHas('employee_contracts', [
            'employee_id' => $profileId,
            'contract_type' => 'contract',
            'status' => 'ended',
        ]);
        $this->assertDatabaseHas('employee_contracts', [
            'employee_id' => $profileId,
            'contract_type' => 'permanent',
            'status' => 'active',
        ]);
    }

    public function test_employee_detail_returns_history_collections(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Detail Histories',
                'email' => 'detail.histories@example.com',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$id, [
                'employmentStatus' => 'probation',
                'startDate' => '2025-01-10',
                'contractType' => 'contract',
                'contractStartDate' => '2025-01-10',
                'contractEndDate' => '2025-12-31',
                'baseSalary' => 6500000,
                'fixedAllowance' => 500000,
                'salaryType' => 'monthly',
                'bankName' => 'BCA',
                'bankAccountNo' => '123123123',
                'npwp' => '12.345.678.9-000.000',
                'taxStatus' => 'TK0',
            ])
            ->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$id)
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'employmentHistory',
                    'assignmentHistory',
                    'compensationHistory',
                    'contractHistory',
                    'bankAccounts',
                    'documents',
                ],
            ]);
    }

    public function test_non_hcm_admin_cannot_list_employees(): void
    {
        $token = $this->bearerToken(false);

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_non_hcm_admin_cannot_view_other_employee(): void
    {
        $token = $this->bearerToken(false);
        $other = User::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$other->id)
            ->assertStatus(403);
    }

    public function test_non_hcm_admin_can_view_self(): void
    {
        $token = $this->bearerToken();
        $self = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/'.$self->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'hcm-admin@example.com')
            ->assertJsonStructure([
                'data' => [
                    'schedule' => [
                        'source',
                        'sourceLabel',
                        'startTime',
                        'endTime',
                        'display',
                        'shiftId',
                        'shiftName',
                    ],
                ],
            ]);
    }

    public function test_non_hcm_admin_can_update_self_profile_subset_only(): void
    {
        $token = $this->bearerToken(false);
        // Get the last created regular user (they have a timestamped email)
        $self = User::latest('id')->first();
        EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $self->id],
            ['employment_status' => 'active'],
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$self->id, [
                'phone' => '081234567890',
                'address' => 'Jakarta',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '6281234567890');

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id])
            ->putJson('/v1/hcm/employees/'.$self->id, [
                'baseSalary' => 999999,
            ])
            ->assertStatus(403);
    }

    public function test_hcm_admin_can_upload_employee_profile_photo(): void
    {
        Storage::fake('public');
        $token = $this->adminBearerToken();
        $admin = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();

        $file = UploadedFile::fake()->image('avatar.png', 300, 300)->size(256);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/v1/hcm/employees/'.$admin->id.'/profile-photo', [
                'photo' => $file,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['profilePhotoUrl']]);

        $profile = EmployeeProfile::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertNotNull($profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);
    }

    public function test_hcm_admin_cannot_upload_profile_photo_for_other_employee(): void
    {
        Storage::fake('public');
        $token = $this->adminBearerToken();

        $employee = User::factory()->create([
            'name' => 'Employee Upload Target',
            'email' => 'employee-upload-target@example.com',
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ],
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id, 'company_id' => $this->company->id],
            ['employment_status' => 'active'],
        );

        $file = UploadedFile::fake()->image('admin-target.png', 200, 200)->size(128);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ])->post('/v1/hcm/employees/'.$employee->id.'/profile-photo', [
            'photo' => $file,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_company_owner_cannot_upload_profile_photo_for_other_employee(): void
    {
        Storage::fake('public');
        $this->adminBearerToken();

        $owner = User::factory()->create([
            'name' => 'Tenant Owner',
            'email' => 'tenant-owner@example.com',
            'password' => bcrypt('OwnerPass1'),
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $owner->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
            ],
        );

        $employee = User::factory()->create([
            'name' => 'Tenant Employee',
            'email' => 'tenant-employee@example.com',
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ],
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id, 'company_id' => $this->company->id],
            ['employment_status' => 'active'],
        );

        $ownerLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => $owner->email,
            'password' => 'OwnerPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();
        $ownerToken = (string) $ownerLogin->json('data.accessToken');

        $file = UploadedFile::fake()->image('owner-try.png', 200, 200)->size(128);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ownerToken,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ])->post('/v1/hcm/employees/'.$employee->id.'/profile-photo', [
            'photo' => $file,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_employee_profile_photo_upload_rejects_non_image_file(): void
    {
        $token = $this->adminBearerToken();
        $admin = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        $file = UploadedFile::fake()->create('bad.pdf', 50, 'application/pdf');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/v1/hcm/employees/'.$admin->id.'/profile-photo', [
                'photo' => $file,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MEDIA',
                ],
            ]);
    }

    public function test_employee_profile_photo_upload_rejects_oversized_image_file(): void
    {
        $token = $this->adminBearerToken();
        $admin = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        $file = UploadedFile::fake()->image('too-big.jpg', 1200, 1200)->size(2300);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->post('/v1/hcm/employees/'.$admin->id.'/profile-photo', [
                'photo' => $file,
            ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MEDIA',
                ],
            ]);
    }

    public function test_hcm_admin_can_delete_employee_profile_photo(): void
    {
        Storage::fake('public');
        $token = $this->adminBearerToken();
        $admin = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();

        $file = UploadedFile::fake()->image('avatar.png', 300, 300)->size(256);

        $upload = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->post('/v1/hcm/employees/'.$admin->id.'/profile-photo', [
            'photo' => $file,
        ]);

        $upload->assertOk();

        $profile = EmployeeProfile::query()->where('user_id', $admin->id)->firstOrFail();
        $this->assertNotNull($profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->delete('/v1/hcm/employees/'.$admin->id.'/profile-photo')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.profilePhotoUrl', null);

        $profile->refresh();
        $this->assertNull($profile->profile_photo_path);
    }

    public function test_hcm_admin_cannot_delete_profile_photo_for_other_employee(): void
    {
        Storage::fake('public');
        $token = $this->adminBearerToken();

        $employee = User::factory()->create([
            'name' => 'Employee Delete Target',
            'email' => 'employee-delete-target@example.com',
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ],
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id, 'company_id' => $this->company->id],
            ['employment_status' => 'active'],
        );

        $file = UploadedFile::fake()->image('seed-delete.png', 300, 300)->size(256);
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ])->post('/v1/hcm/employees/'.$employee->id.'/profile-photo', [
            'photo' => $file,
        ])->assertStatus(403);

        EmployeeProfile::query()->where('user_id', $employee->id)->update([
            'profile_photo_path' => 'avatars/seed-existing.png',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ])->delete('/v1/hcm/employees/'.$employee->id.'/profile-photo')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_company_owner_cannot_delete_profile_photo_for_other_employee(): void
    {
        Storage::fake('public');
        $this->adminBearerToken();

        $owner = User::factory()->create([
            'name' => 'Tenant Owner Delete',
            'email' => 'tenant-owner-delete@example.com',
            'password' => bcrypt('OwnerPass1'),
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $owner->id,
            ],
            [
                'role' => 'owner',
                'status' => 'active',
            ],
        );

        $employee = User::factory()->create([
            'name' => 'Tenant Employee Delete',
            'email' => 'tenant-employee-delete@example.com',
        ]);
        CompanyUser::query()->updateOrCreate(
            [
                'company_id' => $this->company->id,
                'user_id' => $employee->id,
            ],
            [
                'role' => 'employee',
                'status' => 'active',
            ],
        );
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $employee->id, 'company_id' => $this->company->id],
            [
                'employment_status' => 'active',
                'profile_photo_path' => 'avatars/seed-owner-delete.png',
            ],
        );

        $ownerLogin = $this->postJson('/v1/identity/auth/login', [
            'email' => $owner->email,
            'password' => 'OwnerPass1',
            'companyCode' => $this->company->code,
        ])->assertOk();
        $ownerToken = (string) $ownerLogin->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$ownerToken,
            'Accept' => 'application/json',
            'X-Company-Id' => (string) $this->company->id,
        ])->delete('/v1/hcm/employees/'.$employee->id.'/profile-photo')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_employee_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin, 'X-Company-Id' => (string) $this->company->id])
            ->getJson('/v1/hcm/employees/999999')
            ->assertNotFound();
    }

    public function test_non_admin_cannot_access_export_endpoints(): void
    {
        $token = $this->bearerToken(false);
        $headers = ['Authorization' => 'Bearer '.$token, 'X-Company-Id' => (string) $this->company->id];

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/employees/export?format=csv')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/departments/export?format=xlsx')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/designations/export?format=pdf')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->withHeaders($headers)
            ->getJson('/v1/hcm/policies/export?format=xlsx')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}
