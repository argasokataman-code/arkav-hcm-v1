<?php

namespace Tests\Feature;

use App\Models\AuthToken;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\WilayahDistrict;
use App\Models\WilayahProvince;
use App\Models\WilayahRegency;
use App\Models\WilayahVillage;
use App\Support\TenantContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Locks the Global Super Admin (developer / platform maintainer) bypass
 * contract. A user with `users.is_super_admin = 1` must:
 *
 *  - Resolve tenant context for ANY company, even without a `company_users`
 *    membership row (via synthesized virtual membership).
 *  - Employee directory stays scoped to active company.
 *  - Never be blocked by subscription feature gates (tickets, asset mgmt, …).
 */
class GlobalSuperAdminBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_resolver_synthesizes_membership_for_global_admin_on_foreign_company(): void
    {
        $globalAdmin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        $foreignCompany = Company::create([
            'code' => 'FOREIGN1',
            'name' => 'Foreign Tenant',
            'slug' => 'foreign-tenant',
        ]);

        $request = Request::create('/api/whatever', 'GET', server: [
            'HTTP_X-Company-Id' => (string) $foreignCompany->id,
        ]);

        $resolver = app(TenantContextResolver::class);
        $result = $resolver->resolve($request, $globalAdmin);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame($foreignCompany->id, $result['company']->id);
        $this->assertNotNull($result['membership']);
        $this->assertSame('super_admin', $result['membership']->role);

        $this->assertDatabaseMissing('company_users', [
            'user_id' => $globalAdmin->id,
            'company_id' => $foreignCompany->id,
        ]);
    }

    public function test_global_admin_without_explicit_tenant_uses_prioritized_default_company(): void
    {
        config()->set('hcm.super_admin_default_company_code', 'default_company');

        $globalAdmin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        $otherCompany = Company::create([
            'code' => 'alpha_company',
            'name' => 'Alpha Company',
            'slug' => 'alpha-company',
        ]);

        $preferredCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'slug' => 'default-company',
            ]
        );

        CompanyUser::create([
            'user_id' => $globalAdmin->id,
            'company_id' => $otherCompany->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        CompanyUser::create([
            'user_id' => $globalAdmin->id,
            'company_id' => $preferredCompany->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $request = Request::create('/api/whatever', 'GET');

        $resolver = app(TenantContextResolver::class);
        $result = $resolver->resolve($request, $globalAdmin);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame($preferredCompany->id, $result['company']->id);
        $this->assertSame($preferredCompany->code, $result['company']->code);
    }

    public function test_global_admin_with_stale_tenant_header_falls_back_to_default_company(): void
    {
        config()->set('hcm.super_admin_default_company_code', 'default_company');

        $globalAdmin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        $fallbackCompany = Company::query()->firstOrCreate(
            ['code' => 'default_company'],
            [
                'name' => 'Default Company',
                'slug' => 'default-company',
            ]
        );

        $request = Request::create('/api/whatever', 'GET', server: [
            'HTTP_X-Company-Code' => 'stale_missing_company_code',
        ]);

        $resolver = app(TenantContextResolver::class);
        $result = $resolver->resolve($request, $globalAdmin);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame($fallbackCompany->id, $result['company']->id);
        $this->assertSame('super_admin', $result['membership']->role);

        $this->assertDatabaseMissing('company_users', [
            'user_id' => $globalAdmin->id,
            'company_id' => $fallbackCompany->id,
        ]);
    }

    public function test_employee_list_stays_scoped_to_active_tenant_for_global_admin(): void
    {
        [, $companyA, $token] = $this->seedGlobalAdminWithToken();

        $companyB = Company::create([
            'code' => 'OTHERC1',
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
        ]);

        $employeeA = User::factory()->create(['name' => 'Alice From A']);
        EmployeeProfile::create([
            'user_id' => $employeeA->id,
            'company_id' => $companyA->id,
            'employment_status' => 'active',
        ]);

        $employeeB = User::factory()->create(['name' => 'Bob From B']);
        EmployeeProfile::create([
            'user_id' => $employeeB->id,
            'company_id' => $companyB->id,
            'employment_status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Company-Id', (string) $companyA->id)
            ->getJson('/v1/hcm/employees?perPage=50');

        $response->assertOk();
        $ids = collect($response->json('data') ?? [])
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $this->assertContains($employeeA->id, $ids, 'Global admin must see Company A employees.');
        $this->assertNotContains($employeeB->id, $ids, 'Global admin must not see Company B employees outside active tenant.');
    }

    public function test_ticket_feature_gate_does_not_block_global_admin(): void
    {
        [, $company, $token] = $this->seedGlobalAdminWithToken();

        // Company intentionally has NO subscription, hence no ticket feature.
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->withHeader('X-Company-Id', (string) $company->id)
            ->getJson('/v1/hcm/tickets');

        $response->assertOk();
        $this->assertNotSame('SUBSCRIPTION_REQUIRED', $response->json('error.code'));
    }

    public function test_global_admin_can_create_employee_without_active_subscription(): void
    {
        [, $company, $token] = $this->seedGlobalAdminWithToken();

        [$department, $designation] = $this->seedEmployeeOrgCatalog();
        $team = Team::query()->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'name' => 'Platform Ops',
            'is_active' => true,
        ]);
        $region = $this->seedWilayahSelection();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/v1/hcm/employees', array_merge([
                'name' => 'Global Admin Hire',
                'email' => 'global-admin-hire@example.com',
                'password' => 'StrongPass1',
                'confirmPassword' => 'StrongPass1',
                'team' => 'Platform Ops',
                'teamId' => $team->id,
                'departmentId' => $department->id,
                'designationId' => $designation->id,
                'employeeType' => 'permanent',
                'employmentStatus' => 'active',
                'startDate' => '2026-04-25',
                'phone' => '081234567890',
                'nik' => '3174011708980001',
                'placeOfBirth' => 'Jakarta',
                'dateOfBirth' => '1998-08-17',
                'gender' => 'female',
                'maritalStatus' => 'single',
                'religion' => 'Islam',
                'nationality' => 'Indonesia',
                'addressDetail' => 'Jl. Global Admin No. 1',
                'baseSalary' => 6500000,
                'fixedAllowance' => 500000,
                'salaryType' => 'monthly',
                'contractType' => 'permanent',
                'contractStatus' => 'active',
                'contractStartDate' => '2026-04-25',
                'bankName' => 'BCA',
                'bankAccountNo' => '1234567890',
                'bankAccountHolderName' => 'Global Admin Hire',
                'emergencyContacts' => [
                    [
                        'name' => 'Emergency Contact',
                        'relationship' => 'Sibling',
                        'phone' => '081234567891',
                    ],
                ],
            ], $region));

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'global-admin-hire@example.com');

        $userId = (int) $response->json('data.id');

        $this->assertDatabaseHas('employee_profiles', [
            'company_id' => $company->id,
            'user_id' => $userId,
            'department_id' => $department->id,
            'designation_id' => $designation->id,
            'employment_status' => 'active',
        ]);
    }

    /**
     * @return array{0: User, 1: Company, 2: string}
     */
    private function seedGlobalAdminWithToken(): array
    {
        $company = Company::create([
            'code' => 'HOMEC01',
            'name' => 'Home Tenant',
            'slug' => 'home-tenant',
        ]);

        $admin = User::factory()->create([
            'email' => 'platform.dev@example.com',
            'is_super_admin' => true,
        ]);

        CompanyUser::create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        $rawToken = bin2hex(random_bytes(32));
        AuthToken::query()->create([
            'user_id' => $admin->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDay(),
        ]);

        return [$admin, $company, $rawToken];
    }

    /**
     * @return array{0: Department, 1: Designation}
     */
    private function seedEmployeeOrgCatalog(): array
    {
        $department = Department::query()->create([
            'name' => 'Engineering',
            'code' => 'ENG',
            'is_active' => true,
        ]);

        $designation = Designation::query()->create([
            'department_id' => $department->id,
            'name' => 'Senior Developer',
            'code' => 'SRDEV',
            'is_active' => true,
        ]);

        return [$department, $designation];
    }

    /**
     * @return array<string, int>
     */
    private function seedWilayahSelection(): array
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
}
