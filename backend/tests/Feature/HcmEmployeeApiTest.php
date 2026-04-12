<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\Company;
use App\Models\Policy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

#[IgnoreDeprecations]
class HcmEmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private function bearerToken(): string
    {
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Hcm Admin',
            'email' => 'hcm-admin@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => 'hcm-admin@example.com',
            'password' => 'StrongPass1',
        ]);

        $login->assertOk();
        $token = $login->json('data.accessToken');
        $this->assertNotEmpty($token);

        return $token;
    }

    private function adminBearerToken(): string
    {
        $token = $this->bearerToken();
        $user = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'team' => 'HR',
                'designation' => 'Manager',
                'employment_status' => 'active',
            ],
        );

        return $token;
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

        return array_merge([
            'name' => 'Valid Employee',
            'email' => 'valid.employee@example.com',
            'password' => 'StrongPass1',
            'confirmPassword' => 'StrongPass1',
            'team' => 'Engineering',
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
            'address' => 'Jl. Jenderal Sudirman No. 1, Jakarta',
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
        ], $overrides);
    }

    public function test_employees_list_requires_auth(): void
    {
        $this->getJson('/v1/hcm/employees')->assertStatus(401);
    }

    public function test_employees_list_returns_summary_and_rows(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
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
            'team' => 'Engineering',
            'designation' => 'Developer',
            'employment_status' => 'probation',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/employees/'.$id, [
                'employmentStatus' => 'active',
                'phone' => '081234567800',
            ])
            ->assertOk()
            ->assertJsonPath('data.employmentStatus', 'active')
            ->assertJsonPath('data.phone', '081234567800');
    }

    public function test_employee_profile_can_store_pkwt_contract_fields(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWT Staff',
                'email' => 'pkwtstaff@example.com',
                'team' => 'Operations',
                'employeeType' => 'contract',
                'employmentStatus' => 'active',
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Identity Staff',
                'email' => 'identity.staff@example.com',
            ]));

        $create->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $id,
            'nik' => '3174011708980001',
            'place_of_birth' => 'Jakarta',
            'gender' => 'female',
            'marital_status' => 'single',
            'religion' => 'Islam',
            'nationality' => 'Indonesia',
        ]);

        $profile = EmployeeProfile::query()->where('user_id', $id)->firstOrFail();
        $this->assertSame('1998-08-17', optional($profile->date_of_birth)->toDateString());
    }

    public function test_employee_create_rejects_invalid_formats_and_missing_required_fields(): void
    {
        $token = $this->adminBearerToken();

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
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
                'address',
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

    public function test_employee_contract_rules_require_end_date_only_for_pkwt(): void
    {
        $token = $this->adminBearerToken();

        $missingEndDate = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWT Missing End',
                'email' => 'pkwt.missing.end@example.com',
                'contractType' => 'contract',
                'contractEndDate' => null,
                'employeeType' => 'contract',
            ]));

        $missingEndDate->assertStatus(422)
            ->assertJsonValidationErrors(['contractEndDate']);

        $unexpectedEndDate = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'PKWTT With End',
                'email' => 'pkwtt.with.end@example.com',
                'contractType' => 'permanent',
                'contractEndDate' => '2026-01-31',
            ]));

        $unexpectedEndDate->assertStatus(422)
            ->assertJsonValidationErrors(['contractEndDate']);
    }

    public function test_employees_filter_by_status(): void
    {
        $token = $this->adminBearerToken();

        $u = User::factory()->create(['email' => 'inactive@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $u->id,
            'employment_status' => 'inactive',
            'team' => 'X',
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/employees?status=inactive&perPage=50');

        $res->assertOk();
        $ids = collect($res->json('data'))->pluck('id')->all();
        $this->assertContains($u->id, $ids);
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

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Normalized Employee',
                'email' => 'normalized.employee@example.com',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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
                'bpjsKesehatanNo' => 'BPKES001',
                'bpjsKetenagakerjaanNo' => 'BPTK001',
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
            'bpjs_kesehatan_no' => 'BPKES001',
        ]);
        $this->assertSame(1, DB::table('employee_emergency_contacts')->where('employee_id', $profileId)->count());
        $this->assertSame(1, DB::table('employee_educations')->where('employee_id', $profileId)->count());
        $this->assertSame(1, DB::table('employee_experiences')->where('employee_id', $profileId)->count());
    }

    public function test_departments_returns_designation_count_key(): void
    {
        $token = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Export Employee',
                'email' => 'export.employee@example.com',
            ]))
            ->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/employees/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('content-disposition');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/employees/export?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/departments/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/designations/export?format=pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/policies/export?format=xlsx')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_bulk_template_and_upload_requires_hcm_admin(): void
    {
        $token = $this->bearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/employees/bulk-template')
            ->assertStatus(403);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "employee_no,name,email,password,confirm_password,team,designation,employment_status,base_salary,fixed_allowance\n,No Admin,noadmin@example.com,StrongPass1,StrongPass1,HR,Staff,active,5000000,600000\n",
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file])
            ->assertStatus(403);
    }

    public function test_bulk_template_download_contains_reference_sheets(): void
    {
        $token = $this->adminBearerToken();
        $department = Department::query()->create(['name' => 'People Operations', 'code' => 'POPS', 'is_active' => true]);
        Designation::query()->create([
            'department_id' => $department->id,
            'name' => 'HR Generalist',
            'code' => 'HRGEN',
            'is_active' => true,
        ]);
        DB::table('teams')->insert([
            'department_id' => $department->id,
            'name' => 'Talent Acquisition',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
        @unlink($tmpPath);
    }

    public function test_hcm_admin_can_bulk_upload_full_employee_data_from_csv(): void
    {
        $token = $this->adminBearerToken();
        $employee = User::factory()->create(['email' => 'employee1@example.com']);
        EmployeeProfile::query()->create([
            'user_id' => $employee->id,
            'employment_status' => 'active',
            'base_salary' => 0,
            'fixed_allowance' => 0,
        ]);
        $employeeNo = sprintf('EMP-%04d', $employee->id);

        $file = UploadedFile::fake()->createWithContent(
            'employees.csv',
            "employee_no,name,email,password,confirm_password,team,designation,employment_status,base_salary,fixed_allowance,phone,address,bio,bank_name,bank_account_no,bank_ifsc_code,bank_branch\n"
            ."{$employeeNo},Employee One,employee1@example.com,,,HR,Lead Staff,active,5500000,700000,08123,Jakarta,Senior Staff,BCA,123456,BCA001,Jakarta\n"
            .",Employee Two,employee2@example.com,StrongPass1,StrongPass1,Finance,Analyst,probation,6000000,800000,08234,Bandung,,Mandiri,98765,MDR001,Bandung\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.createdRows', 1)
            ->assertJsonPath('data.updatedRows', 1)
            ->assertJsonPath('data.failedRows', 0);

        $this->assertDatabaseHas('employee_profiles', [
            'user_id' => $employee->id,
            'base_salary' => 5500000,
            'fixed_allowance' => 700000,
            'team' => 'HR',
            'designation' => 'Lead Staff',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'employee2@example.com',
            'name' => 'Employee Two',
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->get('/v1/hcm/employees/bulk-template')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_bulk_upload_rejects_invalid_enums_and_rolls_back_all_rows(): void
    {
        $token = $this->adminBearerToken();

        $file = UploadedFile::fake()->createWithContent(
            'employees-invalid.csv',
            "employee_no,name,email,password,confirm_password,team,designation,employment_status,base_salary,fixed_allowance,salary_type,contract_type,gender,marital_status,bank_name,tax_status\n"
            .",Valid Employee,valid.rollback@example.com,StrongPass1,StrongPass1,HR,Staff,active,5000000,500000,monthly,permanent,male,single,BCA,TK0\n"
            .",Broken Employee,broken.rollback@example.com,StrongPass1,StrongPass1,HR,Staff,active,4000000,300000,weekly,invalid_contract,robot,complicated,Bank Khayalan,TK9\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'BULK_UPLOAD_VALIDATION_FAILED')
            ->assertJsonPath('data.createdRows', 0)
            ->assertJsonPath('data.updatedRows', 0);

        $this->assertDatabaseMissing('users', ['email' => 'valid.rollback@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'broken.rollback@example.com']);
    }

    public function test_bulk_upload_rejects_conflicting_employee_no_and_email_mapping(): void
    {
        $token = $this->adminBearerToken();
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

        $employeeNo = sprintf('EMP-%04d', $first->id);
        $file = UploadedFile::fake()->createWithContent(
            'employees-conflict.csv',
            "employee_no,name,email,password,confirm_password,team,designation,employment_status,base_salary,fixed_allowance\n"
            ."{$employeeNo},Conflict Row,bulk.conflict.second@example.com,,,HR,Staff,active,5000000,500000\n",
        );

        $upload = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->post('/v1/hcm/employees/bulk-upload', ['file' => $file]);

        $upload->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'BULK_UPLOAD_VALIDATION_FAILED');

        $errors = $upload->json('data.errors') ?? [];
        $this->assertIsArray($errors);
        $this->assertNotEmpty(array_filter($errors, fn ($item) => str_contains((string) $item, 'employee_no dan email mengacu ke user yang berbeda')));
    }

    public function test_contract_transition_to_pkwtt_keeps_history(): void
    {
        $token = $this->adminBearerToken();

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Contract Transition',
                'email' => 'transition.contract@example.com',
                'employmentStatus' => 'active',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');
        $profileId = (int) EmployeeProfile::query()->where('user_id', $id)->value('id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/employees/'.$id, [
                'contractType' => 'contract',
                'contractStartDate' => '2025-01-01',
                'contractEndDate' => '2025-12-31',
            ])
            ->assertOk()
            ->assertJsonPath('data.contract.contractType', 'contract');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $create = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/v1/hcm/employees', $this->validEmployeePayload([
                'name' => 'Detail Histories',
                'email' => 'detail.histories@example.com',
            ]))
            ->assertStatus(201);

        $id = (int) $create->json('data.id');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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
        $token = $this->bearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/employees')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_non_hcm_admin_cannot_view_other_employee(): void
    {
        $token = $this->bearerToken();
        $other = User::factory()->create();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/v1/hcm/employees/'.$other->id)
            ->assertStatus(403);
    }

    public function test_non_hcm_admin_can_view_self(): void
    {
        $token = $this->bearerToken();
        $self = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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
        $token = $this->bearerToken();
        $self = User::query()->where('email', 'hcm-admin@example.com')->firstOrFail();
        EmployeeProfile::query()->firstOrCreate(
            ['user_id' => $self->id],
            ['employment_status' => 'active'],
        );

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/v1/hcm/employees/'.$self->id, [
                'phone' => '081234567890',
                'address' => 'Jakarta',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone', '081234567890');

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
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

    public function test_employee_show_returns_404_when_not_found(): void
    {
        $admin = $this->adminBearerToken();

        $this->withHeaders(['Authorization' => 'Bearer '.$admin])
            ->getJson('/v1/hcm/employees/999999')
            ->assertNotFound();
    }
}
