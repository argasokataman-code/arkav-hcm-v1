<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmRole;
use App\Models\HcmPermission;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\User;
use App\Services\HcmRbacService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HcmRbacIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected HcmRbacService $rbacService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rbacService = app(HcmRbacService::class);
    }

    #[Test]
    public function user_from_company_a_cannot_access_company_b_data()
    {
        // Create two companies
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        // Create users
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create memberships
        CompanyUser::create([
            'company_id' => $companyA->id,
            'user_id' => $userA->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        CompanyUser::create([
            'company_id' => $companyB->id,
            'user_id' => $userB->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        // Create roles and permissions for each company
        $roleA = HcmRole::create([
            'company_id' => $companyA->id,
            'code' => 'test_role_a',
            'name' => 'Test Role A',
        ]);

        $roleB = HcmRole::create([
            'company_id' => $companyB->id,
            'code' => 'test_role_b',
            'name' => 'Test Role B',
        ]);

        $permission = HcmPermission::create([
            'code' => 'test.permission',
            'module' => 'test',
            'resource' => 'resource',
            'action' => 'view',
            'name' => 'Test Permission',
        ]);

        // Assign permissions to roles
        HcmRolePermission::withoutTimestamps(function () use ($roleA, $roleB, $permission, $companyA, $companyB): void {
            HcmRolePermission::create([
                'role_id' => $roleA->id,
                'permission_id' => $permission->id,
                'company_id' => $companyA->id,
            ]);

            HcmRolePermission::create([
                'role_id' => $roleB->id,
                'permission_id' => $permission->id,
                'company_id' => $companyB->id,
            ]);
        });

        // Assign roles to users
        HcmUserRole::create([
            'user_id' => $userA->id,
            'company_id' => $companyA->id,
            'role_id' => $roleA->id,
            'status' => 'active',
        ]);

        HcmUserRole::create([
            'user_id' => $userB->id,
            'company_id' => $companyB->id,
            'role_id' => $roleB->id,
            'status' => 'active',
        ]);

        // Test: User A should have permission in Company A
        $this->assertTrue($this->rbacService->userHasPermission($userA, 'test.permission', $companyA->id));

        // Test: User A should NOT have permission in Company B
        $this->assertFalse($this->rbacService->userHasPermission($userA, 'test.permission', $companyB->id));

        // Test: User B should have permission in Company B
        $this->assertTrue($this->rbacService->userHasPermission($userB, 'test.permission', $companyB->id));

        // Test: User B should NOT have permission in Company A
        $this->assertFalse($this->rbacService->userHasPermission($userB, 'test.permission', $companyA->id));
    }

    #[Test]
    public function role_without_permission_cannot_access_endpoint()
    {
        $company = Company::factory()->create();
        $user = User::factory()->create();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $role = HcmRole::create([
            'company_id' => $company->id,
            'code' => 'limited_role',
            'name' => 'Limited Role',
        ]);

        // Role has NO permissions
        HcmUserRole::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        // Should not have any permission
        $this->assertFalse($this->rbacService->userHasPermission($user, 'employee.view', $company->id));
        $this->assertFalse($this->rbacService->userHasPermission($user, 'payroll.run', $company->id));
    }

    #[Test]
    public function tenant_admin_with_company_admin_role_can_create_roles()
    {
        $company = Company::factory()->create();
        $email = 'tenant.admin.'.time().'@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        // User is NOT super admin
        $this->assertFalse($this->rbacService->isGlobalAdmin($user));

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        // Tenant admin (CompanyUser role='admin') passes the backward-compat permission check
        // and can manage role setup inside the active tenant context.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/user-management/roles', [
            'company_id' => $company->id,
            'code' => 'test_role',
            'name' => 'Test Role',
        ]);

        $response->assertStatus(201)
                ->assertJsonPath('success', true);
    }

    #[Test]
    public function global_admin_can_manage_tenant_role_setup_with_active_context()
    {
        $company = Company::factory()->create();
        $email = 'super@admin.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Super Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $superUser = User::query()->where('email', $email)->firstOrFail();
        config(['hcm.super_admin_emails' => [$email]]);
        config(['hcm.admin_email' => $email]);

        $this->assertTrue($this->rbacService->isGlobalAdmin($superUser));

        CompanyUser::create([
            'company_id' => $company->id,
            'user_id' => $superUser->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $permission = HcmPermission::query()->firstOrCreate(
            ['code' => 'user_management.manage'],
            [
                'module' => 'user_management',
                'resource' => 'user_management',
                'action' => 'manage',
                'name' => 'Manage User Management',
                'is_active' => true,
            ]
        );

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'SUPER_TEST_ROLE',
            'name' => 'Super Test Role',
            'status' => 'active',
            'is_system' => false,
        ]);

        HcmRolePermission::withoutTimestamps(function () use ($role, $permission, $company): void {
            HcmRolePermission::query()->create([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
                'company_id' => $company->id,
            ]);
        });

        HcmUserRole::query()->create([
            'company_id' => $company->id,
            'user_id' => $superUser->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        // Global admin can manage role setup when operating inside active tenant context.
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/user-management/roles', [
            'company_id' => $company->id,
            'code' => 'super_created_role',
            'name' => 'Super Created Role',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'SUPER_CREATED_ROLE');
    }
}
