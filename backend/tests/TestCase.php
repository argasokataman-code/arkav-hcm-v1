<?php

namespace Tests;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmRolePermission;
use App\Models\HcmUserRole;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Assign full HCM admin permissions to a user within a company.
     * Creates role + all core permissions if needed.
     *
     * @param User $user
     * @param Company|null $company
     * @return HcmRole
     */
    protected function setupHcmAdminPermissions(User $user, ?Company $company = null): HcmRole
    {
        if (! $company) {
            $company = $this->createIsolatedTestCompany();
        }

        // Ensure user is member of company
        CompanyUser::firstOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role' => 'admin', 'status' => 'active']
        );

        // Create HCM admin role
        $role = HcmRole::query()->firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HCM_ADMIN'],
            ['name' => 'HCM Admin', 'status' => 'active']
        );

        // List of core permissions needed for most feature tests
        $permissionCodes = [
            'employee.manage' => ['module' => 'employee', 'resource' => 'employee', 'action' => 'manage'],
            'employee.view' => ['module' => 'employee', 'resource' => 'employee', 'action' => 'view'],
            'department.manage' => ['module' => 'department', 'resource' => 'department', 'action' => 'manage'],
            'designation.manage' => ['module' => 'designation', 'resource' => 'designation', 'action' => 'manage'],
            'policy.manage' => ['module' => 'policy', 'resource' => 'policy', 'action' => 'manage'],
            'team.manage' => ['module' => 'organization', 'resource' => 'team', 'action' => 'manage'],
            'team.lead' => ['module' => 'organization', 'resource' => 'team', 'action' => 'lead'],
            'attendance.manage' => ['module' => 'attendance', 'resource' => 'attendance', 'action' => 'manage'],
            'leave.manage' => ['module' => 'leave', 'resource' => 'leave', 'action' => 'manage'],
            'payroll.view' => ['module' => 'payroll', 'resource' => 'payroll', 'action' => 'view'],
            'payroll.manage' => ['module' => 'payroll', 'resource' => 'payroll', 'action' => 'manage'],
            'payroll.finalize' => ['module' => 'payroll', 'resource' => 'payroll', 'action' => 'finalize'],
            'payroll.disburse' => ['module' => 'payroll', 'resource' => 'payroll', 'action' => 'disburse'],
            'promotion.manage' => ['module' => 'promotion', 'resource' => 'promotion', 'action' => 'manage'],
            'resignation.manage' => ['module' => 'resignation', 'resource' => 'resignation', 'action' => 'manage'],
            'termination.manage' => ['module' => 'termination', 'resource' => 'termination', 'action' => 'manage'],
            'ticket.manage' => ['module' => 'ticket', 'resource' => 'ticket', 'action' => 'manage'],
            'training.manage' => ['module' => 'training', 'resource' => 'training', 'action' => 'manage'],
            'performance.manage' => ['module' => 'performance', 'resource' => 'performance', 'action' => 'manage'],
            'goal.manage' => ['module' => 'goal', 'resource' => 'goal', 'action' => 'manage'],
            'overtime.manage' => ['module' => 'overtime', 'resource' => 'overtime', 'action' => 'manage'],
        ];

        $permissions = [];
        foreach ($permissionCodes as $code => $attrs) {
            $permission = HcmPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'module' => $attrs['module'],
                    'resource' => $attrs['resource'],
                    'action' => $attrs['action'],
                    'name' => ucfirst(str_replace('.', ' ', $code)),
                    'is_active' => true,
                ]
            );
            $permissions[] = $permission->id;
        }

        // Sync permissions to role
        HcmRolePermission::withoutTimestamps(function () use ($role, $permissions, $company): void {
            // Delete existing permissions first
            HcmRolePermission::query()
                ->where('role_id', $role->id)
                ->where('company_id', $company->id)
                ->delete();

            // Recreate with new permissions
            foreach ($permissions as $permissionId) {
                HcmRolePermission::create([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'company_id' => $company->id,
                ]);
            }
        });

        // Assign role to user
        HcmUserRole::updateOrCreate(
            ['user_id' => $user->id, 'company_id' => $company->id],
            ['role_id' => $role->id, 'status' => 'active']
        );

        return $role;
    }

    /**
     * Create a user with HCM admin permissions and return both token and company ID.
     *
     * @param array $userData
     * @param Company|null $company
     * @return array{token: string, company_id: int, company: Company}
     */
    protected function createHcmAdminWithCompany(array $userData = [], ?Company $company = null): array
    {
        $defaults = [
            'name' => 'Test HCM Admin',
            'email' => 'test-hcm-admin-'.time().'@example.com',
            'password' => 'StrongPass1',
        ];
        $data = array_merge($defaults, $userData);

        // Register user
        $this->postJson('/v1/identity/auth/register', [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'confirmPassword' => $data['password'],
        ])->assertStatus(201);

        // Get user
        $user = User::query()->where('email', $data['email'])->firstOrFail();

        // Setup HCM permissions
        if (! $company) {
            $company = $this->createIsolatedTestCompany();
        }

        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__')) {
            Subscription::query()->where('company_id', $company->id)->delete();
        }

        $this->setupHcmAdminPermissions($user, $company);

        // Login with company code
        $login = $this->postJson('/v1/identity/auth/login', [
            'email' => $data['email'],
            'password' => $data['password'],
            'companyCode' => $company->code,
        ])->assertOk();

        return [
            'token' => (string) $login->json('data.accessToken'),
            'company_id' => $company->id,
            'company' => $company,
        ];
    }

    /**
     * Create a user with HCM admin permissions and return bearer token.
     *
     * @param array $userData
     * @param Company|null $company
     * @return string Bearer token
     */
    protected function createHcmAdminUserWithToken(array $userData = [], ?Company $company = null): string
    {
        $result = $this->createHcmAdminWithCompany($userData, $company);
        return $result['token'];
    }
    
    /**
     * Add tenant context headers for API requests.
     *
     * @param array $headers
     * @param int|Company $companyOrId
     * @return array Headers with X-Company-Id added
     */
    protected function withCompanyContext(array $headers = [], int|Company $companyOrId = 1): array
    {
        $companyId = $companyOrId instanceof Company ? $companyOrId->id : $companyOrId;
        return array_merge($headers, ['X-Company-Id' => (string) $companyId]);
    }

    protected function createIsolatedTestCompany(array $attributes = []): Company
    {
        static $counter = 0;
        $counter++;

        $code = strtoupper(substr('TST'.bin2hex(random_bytes(4)).$counter, 0, 12));

        return Company::query()->create(array_merge([
            'code' => $code,
            'name' => 'Test Company '.$counter,
            'legal_name' => 'Test Company '.$counter.' LLC',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'IDR',
            'country_code' => 'ID',
        ], $attributes));
    }
}
