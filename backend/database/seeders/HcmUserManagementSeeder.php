<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\HcmPermission;
use App\Models\HcmRole;
use App\Models\HcmUserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class HcmUserManagementSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = Company::query()->orderBy('id')->value('id');
        if (! $companyId) {
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

        $roles = [
            ['code' => 'ADMIN', 'name' => 'Administrator', 'isSystem' => true],
            ['code' => 'HR_ADMIN', 'name' => 'HR Administrator', 'isSystem' => true],
            ['code' => 'OPS_ADMIN', 'name' => 'Operations Administrator', 'isSystem' => true],
            ['code' => 'OWNER', 'name' => 'Owner'],
            ['code' => 'HCM_ADMIN', 'name' => 'HCM Admin'],
            ['code' => 'MANAGER', 'name' => 'Manager'],
            ['code' => 'EMPLOYEE', 'name' => 'Employee'],
        ];

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
            $createdRoles[$role['code']] = $createdRole->id;
        }

        // Assign ADMIN role to the default QA admin user
        $adminEmail = config('hcm.admin_email', 'qa.login@example.com');
        $adminUser = User::query()->where('email', $adminEmail)->first();
        if ($adminUser) {
            HcmUserRole::query()->updateOrCreate(
                [
                    'user_id' => $adminUser->id,
                    'company_id' => $companyId,
                    'role_id' => $createdRoles['ADMIN'],
                ],
                [
                    'status' => 'active',
                ]
            );
        }
    }
}
