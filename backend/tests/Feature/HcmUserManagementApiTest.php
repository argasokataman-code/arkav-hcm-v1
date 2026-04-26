<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HcmUserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        $email = 'tenant.admin@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'QA Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $companyId = (int) CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');
        $this->assertGreaterThan(0, $companyId);

        $requiredPermissions = [
            ['code' => 'user.view', 'module' => 'user_management', 'resource' => 'user', 'action' => 'view', 'name' => 'View Users'],
            ['code' => 'user.create', 'module' => 'user_management', 'resource' => 'user', 'action' => 'create', 'name' => 'Create Users'],
            ['code' => 'user.update', 'module' => 'user_management', 'resource' => 'user', 'action' => 'update', 'name' => 'Update Users'],
            ['code' => 'user.assign_role', 'module' => 'user_management', 'resource' => 'user_role', 'action' => 'assign', 'name' => 'Assign User Roles'],
            ['code' => 'role.view', 'module' => 'user_management', 'resource' => 'role', 'action' => 'view', 'name' => 'View Roles'],
            ['code' => 'role.create', 'module' => 'user_management', 'resource' => 'role', 'action' => 'create', 'name' => 'Create Roles'],
            ['code' => 'role.update', 'module' => 'user_management', 'resource' => 'role', 'action' => 'update', 'name' => 'Update Roles'],
            ['code' => 'role.delete', 'module' => 'user_management', 'resource' => 'role', 'action' => 'delete', 'name' => 'Delete Roles'],
            ['code' => 'role.sync_permission', 'module' => 'user_management', 'resource' => 'role_permission', 'action' => 'sync', 'name' => 'Sync Role Permissions'],
            ['code' => 'user_management.view', 'module' => 'user_management', 'resource' => 'user_management', 'action' => 'view', 'name' => 'View User Management'],
            ['code' => 'user_management.manage', 'module' => 'user_management', 'resource' => 'user_management', 'action' => 'manage', 'name' => 'Manage User Management'],
        ];

        foreach ($requiredPermissions as $permissionData) {
            HcmPermission::query()->updateOrCreate(
                ['code' => $permissionData['code']],
                $permissionData + ['description' => null, 'is_active' => true]
            );
        }

        $adminRole = HcmRole::query()->updateOrCreate(
            ['company_id' => $companyId, 'code' => 'TENANT_TEST_ADMIN'],
            ['name' => 'Tenant Test Admin', 'status' => 'active', 'is_system' => false]
        );
        $adminRole->permissions()->sync(
            HcmPermission::query()->whereIn('code', array_column($requiredPermissions, 'code'))->pluck('id')->all()
        );

        HcmUserRole::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'role_id' => $adminRole->id,
            ],
            ['status' => 'active']
        );

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();
        $token = (string) $login->json('data.accessToken');
        $this->assertNotSame('', $token);

        return $token;
    }

    private function employeeToken(): string
    {
        $email = 'employee.user@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Employee User',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $login->assertOk();

        return (string) $login->json('data.accessToken');
    }

    private function activeCompanyIdFor(string $email): int
    {
        $user = User::query()->where('email', $email)->firstOrFail();

        $companyId = CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->value('company_id');

        $this->assertNotNull($companyId);

        return (int) $companyId;
    }

    public function test_user_management_list_supports_filter_and_pagination(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('tenant.admin@example.com');

        $role = HcmRole::query()->create([
            'company_id' => $companyId,
            'code' => 'TEAM_LEAD',
            'name' => 'Team Lead',
            'status' => 'active',
            'is_system' => false,
        ]);

        $users = User::factory()->count(3)->create();
        foreach ($users as $index => $user) {
            CompanyUser::query()->updateOrCreate(
                ['company_id' => $companyId, 'user_id' => $user->id],
                ['role' => 'member', 'status' => $index === 2 ? 'inactive' : 'active', 'joined_at' => now()]
            );
        }

        HcmUserRole::query()->create([
            'user_id' => $users[0]->id,
            'company_id' => $companyId,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])
            ->getJson('/v1/hcm/user-management/users?perPage=2&status=active&roleCode=TEAM_LEAD')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.pagination.perPage', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activeRoleCodes.0', 'TEAM_LEAD');
    }

    public function test_admin_can_crud_roles_and_sync_permissions(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('tenant.admin@example.com');

        HcmPermission::query()->updateOrCreate(
            ['code' => 'user.view'],
            [
                'module' => 'user_management',
                'resource' => 'user',
                'action' => 'view',
                'name' => 'View User',
                'description' => null,
                'is_active' => true,
            ]
        );

        HcmPermission::query()->updateOrCreate(
            ['code' => 'user.update'],
            [
                'module' => 'user_management',
                'resource' => 'user',
                'action' => 'update',
                'name' => 'Update User',
                'description' => null,
                'is_active' => true,
            ]
        );

        $createRole = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/user-management/roles', [
            'code' => 'ops_admin',
            'name' => 'Ops Admin',
            'description' => 'Role for operations admin',
        ]);

        $createRole->assertStatus(201)->assertJsonPath('data.code', 'OPS_ADMIN');
        $roleId = (int) $createRole->json('data.id');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/user-management/roles/'.$roleId.'/permissions:sync', [
            'permissionCodes' => ['user.view', 'user.update'],
        ])
            ->assertOk()
            ->assertJsonPath('data.roleId', $roleId)
            ->assertJsonPath('data.permissionCodes.0', 'user.update');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->putJson('/v1/hcm/user-management/roles/'.$roleId, [
            'name' => 'Ops Admin Updated',
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->deleteJson('/v1/hcm/user-management/roles/'.$roleId)
            ->assertOk();
    }

    public function test_admin_can_create_user_assign_role_and_revoke_assignment(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('tenant.admin@example.com');

        HcmRole::query()->create([
            'company_id' => $companyId,
            'code' => 'STAFF',
            'name' => 'Staff',
            'status' => 'active',
            'is_system' => false,
        ]);

        $create = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->postJson('/v1/hcm/user-management/users', [
            'name' => 'Assigned User',
            'email' => 'assigned.user@example.com',
            'password' => 'StrongPass1',
            'roleCodes' => ['STAFF'],
        ]);

        $create->assertStatus(201)->assertJsonPath('success', true);
        $userId = (int) $create->json('data.id');

        $roleList = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/user-management/users/'.$userId.'/roles');

        $roleList->assertOk()->assertJsonCount(1, 'data');
        $assignmentId = (int) $roleList->json('data.0.assignmentId');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->deleteJson('/v1/hcm/user-management/users/'.$userId.'/roles/'.$assignmentId)
            ->assertOk();
    }

    public function test_admin_can_delete_user_from_active_company(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('tenant.admin@example.com');

        $user = User::factory()->create();
        CompanyUser::query()->create([
            'company_id' => $companyId,
            'user_id' => $user->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $role = HcmRole::query()->create([
            'company_id' => $companyId,
            'code' => 'DELETE_TEST',
            'name' => 'Delete Test',
            'status' => 'active',
            'is_system' => false,
        ]);

        HcmUserRole::query()->create([
            'user_id' => $user->id,
            'company_id' => $companyId,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->deleteJson('/v1/hcm/user-management/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('company_users', [
            'company_id' => $companyId,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('hcm_user_roles', [
            'company_id' => $companyId,
            'user_id' => $user->id,
            'status' => 'revoked',
        ]);
    }

    public function test_admin_can_export_user_management_csv(): void
    {
        $token = $this->adminToken();
        $companyId = $this->activeCompanyIdFor('tenant.admin@example.com');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->get('/v1/hcm/user-management/users/export?format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_non_admin_cannot_access_user_management_endpoints(): void
    {
        $token = $this->employeeToken();
        $companyId = $this->activeCompanyIdFor('employee.user@example.com');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyId,
        ])->getJson('/v1/hcm/user-management/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_admin_cannot_access_different_company_user_management(): void
    {
        // Create company A and company B
        $companyA = Company::factory()->create(['code' => 'test_company_a', 'name' => 'Test Company A']);
        $companyB = Company::factory()->create(['code' => 'test_company_b', 'name' => 'Test Company B']);

        // Create a company-scoped admin (not global admin) for company A
        $email = 'admin.company.a.' . time() . '@example.com'; // Unique email
        $password = 'StrongPass1';

        // Register the user  
        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Company A Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        // Get the created user
        $companyAAdmin = User::query()->where('email', $email)->firstOrFail();

        // Add user to company A - registration creates a default company, so let's update it
        CompanyUser::query()->where('user_id', $companyAAdmin->id)->update([
            'company_id' => $companyA->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        // Assign ADMIN role in company A
        $adminRole = HcmRole::query()->create([
            'company_id' => $companyA->id,
            'code' => 'ADMIN',
            'name' => 'Administrator',
            'status' => 'active',
            'is_system' => true,
        ]);

        HcmUserRole::query()->create([
            'user_id' => $companyAAdmin->id,
            'company_id' => $companyA->id,
            'role_id' => $adminRole->id,
            'status' => 'active',
        ]);

        // Add same user to company B as member only (no admin role)
        CompanyUser::query()->create([
            'user_id' => $companyAAdmin->id,
            'company_id' => $companyB->id,
            'role' => 'member',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        // Login to get token
        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $token = (string) $login->json('data.accessToken');

        // Admin should be able to access company A
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyA->id,
        ])->getJson('/v1/hcm/user-management/users')
            ->assertOk()
            ->assertJsonPath('success', true);

        // But should NOT be able to access company B (no admin role there)
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $companyB->id,
        ])->getJson('/v1/hcm/user-management/users')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_tenant_admin_can_configure_role_setup_with_tenant_permissions(): void
    {
        $company = Company::factory()->create(['code' => 'tenant_role_setup_lock']);

        $email = 'tenant-admin-role-setup@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant Role Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();

        CompanyUser::query()->where('user_id', $user->id)->delete();
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach ([
            ['code' => 'role.create', 'module' => 'user_management', 'resource' => 'role', 'action' => 'create', 'name' => 'Create Roles'],
            ['code' => 'role.sync_permission', 'module' => 'user_management', 'resource' => 'role_permission', 'action' => 'sync', 'name' => 'Sync Role Permissions'],
            ['code' => 'user_management.manage', 'module' => 'user_management', 'resource' => 'user_management', 'action' => 'manage', 'name' => 'Manage User Management'],
        ] as $permissionData) {
            HcmPermission::query()->updateOrCreate(
                ['code' => $permissionData['code']],
                [
                    'module' => $permissionData['module'],
                    'resource' => $permissionData['resource'],
                    'action' => $permissionData['action'],
                    'name' => $permissionData['name'],
                    'description' => null,
                    'is_active' => true,
                ]
            );
        }

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'TENANT_MANAGER',
            'name' => 'Tenant Manager',
            'status' => 'active',
            'is_system' => false,
        ]);

        $permissionIds = HcmPermission::query()
            ->whereIn('code', ['role.create', 'role.sync_permission', 'user_management.manage'])
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $role->permissions()->sync($permissionIds);

        HcmUserRole::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/user-management/roles', [
            'code' => 'TENANT_ONLY_ROLE',
            'name' => 'Tenant Only Role',
        ])->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'TENANT_ONLY_ROLE');
    }

    public function test_global_super_admin_cannot_modify_tenant_role_setup(): void
    {
        $company = Company::factory()->create(['code' => 'tenant_role_guard']);

        $email = 'global.super.admin@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Global Super Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();
        $user->is_super_admin = true;
        $user->save();

        CompanyUser::query()->where('user_id', $user->id)->delete();
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertOk();
        $token = (string) $login->json('data.accessToken');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/v1/hcm/user-management/roles', [
            'code' => 'BLOCKED_FOR_GLOBAL',
            'name' => 'Blocked For Global',
        ])->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_ROLE_SETUP_FORBIDDEN');
    }

    public function test_tenant_admin_permissions_catalog_hides_system_module(): void
    {
        $company = Company::factory()->create(['code' => 'tenant_permission_catalog']);

        $email = 'tenant-permission-catalog@example.com';
        $password = 'StrongPass1';

        $this->postJson('/v1/identity/auth/register', [
            'name' => 'Tenant Permission Catalog Admin',
            'email' => $email,
            'password' => $password,
            'confirmPassword' => $password,
        ])->assertStatus(201);

        $user = User::query()->where('email', $email)->firstOrFail();

        CompanyUser::query()->where('user_id', $user->id)->delete();
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        foreach ([
            ['code' => 'user_management.view', 'module' => 'user_management', 'resource' => 'user_management', 'action' => 'view', 'name' => 'View User Management'],
            ['code' => 'settings.manage', 'module' => 'system', 'resource' => 'settings', 'action' => 'manage', 'name' => 'Manage Settings'],
            ['code' => 'cron.manage', 'module' => 'system', 'resource' => 'cron', 'action' => 'manage', 'name' => 'Manage Cron Jobs'],
        ] as $permissionData) {
            HcmPermission::query()->updateOrCreate(
                ['code' => $permissionData['code']],
                $permissionData + ['description' => null, 'is_active' => true]
            );
        }

        $role = HcmRole::query()->create([
            'company_id' => $company->id,
            'code' => 'TENANT_VIEWER',
            'name' => 'Tenant Viewer',
            'status' => 'active',
            'is_system' => false,
        ]);

        $role->permissions()->sync(
            HcmPermission::query()->where('code', 'user_management.view')->pluck('id')->all()
        );

        HcmUserRole::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $token = (string) $this->postJson('/v1/identity/auth/login', [
            'email' => $email,
            'password' => $password,
            'companyCode' => $company->code,
        ])->assertOk()->json('data.accessToken');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'X-Company-Id' => (string) $company->id,
        ])->getJson('/v1/hcm/user-management/permissions');

        $response->assertOk();

        $modules = collect($response->json('data'))->pluck('module')->unique()->values()->all();

        $this->assertNotContains('system', $modules);
        $this->assertContains('user_management', $modules);
    }
}
