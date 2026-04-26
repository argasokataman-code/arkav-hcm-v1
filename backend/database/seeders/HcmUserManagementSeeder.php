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
            // User Management
            ['code' => 'user.view', 'module' => 'user_management', 'resource' => 'user', 'action' => 'view', 'name' => 'View Users'],
            ['code' => 'user.create', 'module' => 'user_management', 'resource' => 'user', 'action' => 'create', 'name' => 'Create Users'],
            ['code' => 'user.update', 'module' => 'user_management', 'resource' => 'user', 'action' => 'update', 'name' => 'Update Users'],
            ['code' => 'user.assign_role', 'module' => 'user_management', 'resource' => 'user_role', 'action' => 'assign', 'name' => 'Assign User Roles'],
            ['code' => 'role.view', 'module' => 'user_management', 'resource' => 'role', 'action' => 'view', 'name' => 'View Roles'],
            ['code' => 'role.create', 'module' => 'user_management', 'resource' => 'role', 'action' => 'create', 'name' => 'Create Roles'],
            ['code' => 'role.update', 'module' => 'user_management', 'resource' => 'role', 'action' => 'update', 'name' => 'Update Roles'],
            ['code' => 'role.delete', 'module' => 'user_management', 'resource' => 'role', 'action' => 'delete', 'name' => 'Delete Roles'],
            ['code' => 'role.sync_permission', 'module' => 'user_management', 'resource' => 'role_permission', 'action' => 'sync', 'name' => 'Sync Role Permissions'],

            // Holiday Management
            ['code' => 'holiday.view', 'module' => 'holiday', 'resource' => 'holiday', 'action' => 'view', 'name' => 'View Holidays'],
            ['code' => 'holiday.create', 'module' => 'holiday', 'resource' => 'holiday', 'action' => 'create', 'name' => 'Create Holidays'],
            ['code' => 'holiday.update', 'module' => 'holiday', 'resource' => 'holiday', 'action' => 'update', 'name' => 'Update Holidays'],
            ['code' => 'holiday.sync', 'module' => 'holiday', 'resource' => 'holiday', 'action' => 'sync', 'name' => 'Sync Holidays'],

            // Employee Management
            ['code' => 'employee.view', 'module' => 'employee', 'resource' => 'employee', 'action' => 'view', 'name' => 'View Employees'],
            ['code' => 'employee.create', 'module' => 'employee', 'resource' => 'employee', 'action' => 'create', 'name' => 'Create Employees'],
            ['code' => 'employee.update', 'module' => 'employee', 'resource' => 'employee', 'action' => 'update', 'name' => 'Update Employees'],
            ['code' => 'employee.delete', 'module' => 'employee', 'resource' => 'employee', 'action' => 'delete', 'name' => 'Delete Employees'],
            ['code' => 'employee.export', 'module' => 'employee', 'resource' => 'employee', 'action' => 'export', 'name' => 'Export Employees'],

            // Leave Management
            ['code' => 'leave.view', 'module' => 'leave', 'resource' => 'leave_request', 'action' => 'view', 'name' => 'View Leave Requests'],
            ['code' => 'leave.create', 'module' => 'leave', 'resource' => 'leave_request', 'action' => 'create', 'name' => 'Create Leave Requests'],
            ['code' => 'leave.update', 'module' => 'leave', 'resource' => 'leave_request', 'action' => 'update', 'name' => 'Update Leave Requests'],
            ['code' => 'leave.approve', 'module' => 'leave', 'resource' => 'leave_request', 'action' => 'approve', 'name' => 'Approve Leave Requests'],
            ['code' => 'leave.reject', 'module' => 'leave', 'resource' => 'leave_request', 'action' => 'reject', 'name' => 'Reject Leave Requests'],
            ['code' => 'leave.settings', 'module' => 'leave', 'resource' => 'leave_settings', 'action' => 'manage', 'name' => 'Manage Leave Settings'],
            ['code' => 'leave.type', 'module' => 'leave', 'resource' => 'leave_type', 'action' => 'manage', 'name' => 'Manage Leave Types'],

            // Attendance Management
            ['code' => 'attendance.view', 'module' => 'attendance', 'resource' => 'attendance', 'action' => 'view', 'name' => 'View Attendance'],
            ['code' => 'attendance.create', 'module' => 'attendance', 'resource' => 'attendance', 'action' => 'create', 'name' => 'Create Attendance'],
            ['code' => 'attendance.update', 'module' => 'attendance', 'resource' => 'attendance', 'action' => 'update', 'name' => 'Update Attendance'],
            ['code' => 'attendance.admin', 'module' => 'attendance', 'resource' => 'attendance', 'action' => 'admin', 'name' => 'Admin Attendance Management'],
            ['code' => 'timesheet.view', 'module' => 'attendance', 'resource' => 'timesheet', 'action' => 'view', 'name' => 'View Timesheets'],
            ['code' => 'schedule.view', 'module' => 'attendance', 'resource' => 'schedule', 'action' => 'view', 'name' => 'View Schedules'],
            ['code' => 'schedule.manage', 'module' => 'attendance', 'resource' => 'schedule', 'action' => 'manage', 'name' => 'Manage Schedules'],

            // Payroll Management
            ['code' => 'payroll.view', 'module' => 'payroll', 'resource' => 'payroll', 'action' => 'view', 'name' => 'View Payroll'],
            ['code' => 'payroll.create', 'module' => 'payroll', 'resource' => 'payroll', 'action' => 'create', 'name' => 'Create Payroll'],
            ['code' => 'payroll.update', 'module' => 'payroll', 'resource' => 'payroll', 'action' => 'update', 'name' => 'Update Payroll'],
            ['code' => 'payroll.run', 'module' => 'payroll', 'resource' => 'payroll_run', 'action' => 'run', 'name' => 'Run Payroll'],
            ['code' => 'payroll.finalize', 'module' => 'payroll', 'resource' => 'payroll_run', 'action' => 'finalize', 'name' => 'Finalize Payroll'],
            ['code' => 'payroll.disburse', 'module' => 'payroll', 'resource' => 'payroll_run', 'action' => 'disburse', 'name' => 'Disburse Payroll'],
            ['code' => 'payroll.item.manage', 'module' => 'payroll', 'resource' => 'payroll_item', 'action' => 'manage', 'name' => 'Manage Payroll Items'],
            ['code' => 'payroll.thr.manage', 'module' => 'payroll', 'resource' => 'payroll_thr', 'action' => 'manage', 'name' => 'Manage THR Payroll'],
            ['code' => 'payroll.pkwt.manage', 'module' => 'payroll', 'resource' => 'payroll_pkwt', 'action' => 'manage', 'name' => 'Manage PKWT Compensation'],

            // Overtime Management
            ['code' => 'overtime.view', 'module' => 'overtime', 'resource' => 'overtime_request', 'action' => 'view', 'name' => 'View Overtime Requests'],
            ['code' => 'overtime.create', 'module' => 'overtime', 'resource' => 'overtime_request', 'action' => 'create', 'name' => 'Create Overtime Requests'],
            ['code' => 'overtime.approve', 'module' => 'overtime', 'resource' => 'overtime_request', 'action' => 'approve', 'name' => 'Approve Overtime Requests'],
            ['code' => 'overtime.type.manage', 'module' => 'overtime', 'resource' => 'overtime_type', 'action' => 'manage', 'name' => 'Manage Overtime Types'],

            // Department & Designation Management
            ['code' => 'department.view', 'module' => 'organization', 'resource' => 'department', 'action' => 'view', 'name' => 'View Departments'],
            ['code' => 'department.manage', 'module' => 'organization', 'resource' => 'department', 'action' => 'manage', 'name' => 'Manage Departments'],
            ['code' => 'designation.view', 'module' => 'organization', 'resource' => 'designation', 'action' => 'view', 'name' => 'View Designations'],
            ['code' => 'designation.manage', 'module' => 'organization', 'resource' => 'designation', 'action' => 'manage', 'name' => 'Manage Designations'],
            ['code' => 'policy.view', 'module' => 'organization', 'resource' => 'policy', 'action' => 'view', 'name' => 'View Policies'],
            ['code' => 'policy.manage', 'module' => 'organization', 'resource' => 'policy', 'action' => 'manage', 'name' => 'Manage Policies'],
            ['code' => 'team.manage', 'module' => 'organization', 'resource' => 'team', 'action' => 'manage', 'name' => 'Manage Teams'],
            ['code' => 'team.lead', 'module' => 'organization', 'resource' => 'team', 'action' => 'lead', 'name' => 'Lead Team Scope'],

            // Ticket Management
            ['code' => 'ticket.view', 'module' => 'ticket', 'resource' => 'ticket', 'action' => 'view', 'name' => 'View Tickets'],
            ['code' => 'ticket.create', 'module' => 'ticket', 'resource' => 'ticket', 'action' => 'create', 'name' => 'Create Tickets'],
            ['code' => 'ticket.update', 'module' => 'ticket', 'resource' => 'ticket', 'action' => 'update', 'name' => 'Update Tickets'],
            ['code' => 'ticket.assign', 'module' => 'ticket', 'resource' => 'ticket', 'action' => 'assign', 'name' => 'Assign Tickets'],
            ['code' => 'ticket.category.manage', 'module' => 'ticket', 'resource' => 'ticket_category', 'action' => 'manage', 'name' => 'Manage Ticket Categories'],

            // Performance Management
            ['code' => 'performance.view', 'module' => 'performance', 'resource' => 'performance', 'action' => 'view', 'name' => 'View Performance'],
            ['code' => 'performance.manage', 'module' => 'performance', 'resource' => 'performance', 'action' => 'manage', 'name' => 'Manage Performance'],
            ['code' => 'goal.view', 'module' => 'performance', 'resource' => 'goal', 'action' => 'view', 'name' => 'View Goals'],
            ['code' => 'goal.manage', 'module' => 'performance', 'resource' => 'goal', 'action' => 'manage', 'name' => 'Manage Goals'],

            // Training Management
            ['code' => 'training.view', 'module' => 'training', 'resource' => 'training', 'action' => 'view', 'name' => 'View Training'],
            ['code' => 'training.manage', 'module' => 'training', 'resource' => 'training', 'action' => 'manage', 'name' => 'Manage Training'],
            ['code' => 'trainer.view', 'module' => 'training', 'resource' => 'trainer', 'action' => 'view', 'name' => 'View Trainers'],
            ['code' => 'trainer.manage', 'module' => 'training', 'resource' => 'trainer', 'action' => 'manage', 'name' => 'Manage Trainers'],

            // Promotion, Resignation, Termination
            ['code' => 'promotion.view', 'module' => 'hr_actions', 'resource' => 'promotion', 'action' => 'view', 'name' => 'View Promotions'],
            ['code' => 'promotion.manage', 'module' => 'hr_actions', 'resource' => 'promotion', 'action' => 'manage', 'name' => 'Manage Promotions'],
            ['code' => 'resignation.view', 'module' => 'hr_actions', 'resource' => 'resignation', 'action' => 'view', 'name' => 'View Resignations'],
            ['code' => 'resignation.manage', 'module' => 'hr_actions', 'resource' => 'resignation', 'action' => 'manage', 'name' => 'Manage Resignations'],
            ['code' => 'termination.view', 'module' => 'hr_actions', 'resource' => 'termination', 'action' => 'view', 'name' => 'View Terminations'],
            ['code' => 'termination.manage', 'module' => 'hr_actions', 'resource' => 'termination', 'action' => 'manage', 'name' => 'Manage Terminations'],

            // Dashboard & Reports
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'resource' => 'dashboard', 'action' => 'view', 'name' => 'View Dashboard'],
            ['code' => 'report.view', 'module' => 'report', 'resource' => 'report', 'action' => 'view', 'name' => 'View Reports'],
            ['code' => 'report.export', 'module' => 'report', 'resource' => 'report', 'action' => 'export', 'name' => 'Export Reports'],

            // System Administration
            ['code' => 'settings.view', 'module' => 'system', 'resource' => 'settings', 'action' => 'view', 'name' => 'View Settings'],
            ['code' => 'settings.manage', 'module' => 'system', 'resource' => 'settings', 'action' => 'manage', 'name' => 'Manage Settings'],
            ['code' => 'cron.manage', 'module' => 'system', 'resource' => 'cron', 'action' => 'manage', 'name' => 'Manage Cron Jobs'],
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
            ['code' => 'TEAM_LEAD', 'name' => 'Team Lead'],
            ['code' => 'MANAGER', 'name' => 'Manager'],
            ['code' => 'EMPLOYEE', 'name' => 'Employee'],
        ];

        $adminEmails = array_filter([
            config('hcm.admin_email', 'qa.login@example.com'),
            config('hcm.secondary_admin_email', 'qa.hcm@example.com'),
        ]);

        $adminRoleCodes = ['ADMIN', 'HR_ADMIN', 'OPS_ADMIN', 'HCM_ADMIN', 'OWNER'];
        $rolePermissionCodes = [
            'TEAM_LEAD' => ['employee.view', 'team.lead', 'report.view'],
            'MANAGER' => ['employee.view', 'team.lead'],
        ];
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
                    $createdRole->syncPermissionsForCompany($adminPermissionIds);
                    continue;
                }

                if (array_key_exists($role['code'], $rolePermissionCodes)) {
                    $scopedPermissionIds = collect($rolePermissionCodes[$role['code']])
                        ->map(static fn (string $code) => $permissionIdsByCode[$code] ?? null)
                        ->filter(static fn ($id): bool => is_numeric($id))
                        ->map(static fn ($id): int => (int) $id)
                        ->values()
                        ->all();

                    $createdRole->syncPermissionsForCompany($scopedPermissionIds);
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
