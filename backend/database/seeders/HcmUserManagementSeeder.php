<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class HcmUserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $companyIds = Company::query()
            ->select('id')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($value): int => (int) $value)
            ->all();

        if ($companyIds === []) {
            return;
        }

        $permissions = [
            ['code' => 'user.view', 'module' => 'user_management', 'resource' => 'user', 'action' => 'view', 'name' => 'View Users'],
            ['code' => 'user.create', 'module' => 'user_management', 'resource' => 'user', 'action' => 'create', 'name' => 'Create Users'],
            ['code' => 'user.update', 'module' => 'user_management', 'resource' => 'user', 'action' => 'update', 'name' => 'Update Users'],
            ['code' => 'user.assign_role', 'module' => 'user_management', 'resource' => 'user_role', 'action' => 'assign', 'name' => 'Assign User Roles'],
            ['code' => 'role.view', 'module' => 'user_management', 'resource' => 'role', 'action' => 'view', 'name' => 'View Roles'],
            ['code' => 'role.create', 'module' => 'user_management', 'resource' => 'role', 'action' => 'create', 'name' => 'Create Roles'],
            ['code' => 'role.update', 'module' => 'user_management', 'resource' => 'role', 'action' => 'update', 'name' => 'Update Roles'],
            ['code' => 'role.delete', 'module' => 'user_management', 'resource' => 'role', 'action' => 'delete', 'name' => 'Delete Roles'],
            ['code' => 'role.sync_permission', 'module' => 'user_management', 'resource' => 'role_permission', 'action' => 'sync', 'name' => 'Sync Role Permissions'],
        ];

        foreach ($permissions as $permission) {
            HcmPermission::query()->updateOrCreate(
                ['code' => $permission['code']],
                [
                    'module' => $permission['module'],
                    'resource' => $permission['resource'],
                    'action' => $permission['action'],
                    'name' => $permission['name'],
                    'description' => null,
                    'is_active' => true,
                ]
            );
        }

        $permissionIdsByCode = HcmPermission::query()
            ->whereIn('code', array_values(array_map(static fn (array $item): string => $item['code'], $permissions)))
            ->pluck('id', 'code');

        $roles = [
            ['code' => 'ADMIN', 'name' => 'Administrator', 'isSystem' => true],
            ['code' => 'HR_ADMIN', 'name' => 'HR Administrator', 'isSystem' => true],
            ['code' => 'OPS_ADMIN', 'name' => 'Operations Administrator', 'isSystem' => true],
            ['code' => 'OWNER', 'name' => 'Owner'],
            ['code' => 'HCM_ADMIN', 'name' => 'HCM Admin'],
            ['code' => 'MANAGER', 'name' => 'Manager'],
            ['code' => 'EMPLOYEE', 'name' => 'Employee'],
        ];

        $adminEmails = array_filter([
            config('hcm.admin_email', 'qa.login@example.com'),
            config('hcm.secondary_admin_email', 'qa.hcm@example.com'),
        ]);

        $adminRoleCodes = ['ADMIN', 'HR_ADMIN', 'OPS_ADMIN', 'HCM_ADMIN', 'OWNER'];
        $adminPermissionIds = collect($permissionIdsByCode)
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        foreach ($companyIds as $companyId) {
            $createdRoles = [];
            foreach ($roles as $role) {
                $createdRole = HcmRole::query()->updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'code' => $role['code'],
                    ],
                    [
                        'name' => $role['name'],
                        'description' => null,
                        'status' => 'active',
                        'is_system' => $role['isSystem'] ?? false,
                    ]
                );

                $createdRoles[$role['code']] = (int) $createdRole->id;

                if (in_array($role['code'], $adminRoleCodes, true)) {
                    $createdRole->permissions()->sync($adminPermissionIds);
                }
            }

            if (! isset($createdRoles['ADMIN'])) {
                continue;
            }

            $adminUserIds = CompanyUser::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->whereIn('role', ['owner', 'admin'])
                ->pluck('user_id')
                ->map(static fn ($value): int => (int) $value)
                ->all();

            foreach ($adminEmails as $adminEmail) {
                $adminUser = User::query()->where('email', strtolower(trim((string) $adminEmail)))->first();
                if (! $adminUser) {
                    continue;
                }

                $isMember = CompanyUser::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $adminUser->id)
                    ->where('status', 'active')
                    ->exists();

                if ($isMember) {
                    $adminUserIds[] = (int) $adminUser->id;
                }
            }

            $adminUserIds = array_values(array_unique($adminUserIds));
            foreach ($adminUserIds as $adminUserId) {
                HcmUserRole::query()->updateOrCreate(
                    [
                        'user_id' => $adminUserId,
                        'company_id' => $companyId,
                        'role_id' => $createdRoles['ADMIN'],
                        'status' => 'active',
                    ],
                    [
                        'assigned_by_user_id' => null,
                        'effective_from' => null,
                        'effective_until' => null,
                        'revoked_at' => null,
                    ]
                );
            }
        }
    }
}
