<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HcmPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Employee Management
            ['code' => 'employee.view', 'module' => 'employee', 'resource' => 'profile', 'action' => 'view', 'name' => 'View Employee Profiles', 'description' => 'Can view employee profile information'],
            ['code' => 'employee.create', 'module' => 'employee', 'resource' => 'profile', 'action' => 'create', 'name' => 'Create Employee Profiles', 'description' => 'Can create new employee profiles'],
            ['code' => 'employee.edit', 'module' => 'employee', 'resource' => 'profile', 'action' => 'edit', 'name' => 'Edit Employee Profiles', 'description' => 'Can edit employee profile information'],
            ['code' => 'employee.delete', 'module' => 'employee', 'resource' => 'profile', 'action' => 'delete', 'name' => 'Delete Employee Profiles', 'description' => 'Can delete employee profiles'],
            ['code' => 'team.manage', 'module' => 'organization', 'resource' => 'team', 'action' => 'manage', 'name' => 'Manage Teams', 'description' => 'Can create, update, delete, and reassign team members'],
            ['code' => 'team.lead', 'module' => 'organization', 'resource' => 'team', 'action' => 'lead', 'name' => 'Lead Team Scope', 'description' => 'Can view members for teams led by the current user'],

            // Payroll
            ['code' => 'payroll.view', 'module' => 'payroll', 'resource' => 'run', 'action' => 'view', 'name' => 'View Payroll Runs', 'description' => 'Can view payroll processing runs'],
            ['code' => 'payroll.run', 'module' => 'payroll', 'resource' => 'run', 'action' => 'run', 'name' => 'Execute Payroll Runs', 'description' => 'Can execute payroll processing'],
            ['code' => 'payroll.approve', 'module' => 'payroll', 'resource' => 'run', 'action' => 'approve', 'name' => 'Approve Payroll Runs', 'description' => 'Can approve payroll runs'],

            // Leave Management
            ['code' => 'leave.view', 'module' => 'leave', 'resource' => 'request', 'action' => 'view', 'name' => 'View Leave Requests', 'description' => 'Can view leave requests'],
            ['code' => 'leave.approve', 'module' => 'leave', 'resource' => 'request', 'action' => 'approve', 'name' => 'Approve Leave Requests', 'description' => 'Can approve leave requests'],
            ['code' => 'leave.create', 'module' => 'leave', 'resource' => 'request', 'action' => 'create', 'name' => 'Create Leave Requests', 'description' => 'Can create leave requests'],

            // Attendance
            ['code' => 'attendance.view', 'module' => 'attendance', 'resource' => 'record', 'action' => 'view', 'name' => 'View Attendance Records', 'description' => 'Can view attendance records'],
            ['code' => 'attendance.admin', 'module' => 'attendance', 'resource' => 'record', 'action' => 'admin', 'name' => 'Admin Attendance Records', 'description' => 'Can manage attendance records and corrections'],

            // User Management
            ['code' => 'user_management.view', 'module' => 'user', 'resource' => 'profile', 'action' => 'view', 'name' => 'View User Profiles', 'description' => 'Can view user profile information'],
            ['code' => 'user_management.manage', 'module' => 'user', 'resource' => 'profile', 'action' => 'manage', 'name' => 'Manage Users', 'description' => 'Can create, edit, and manage users'],

            // Role & Permission Management
            ['code' => 'role.view', 'module' => 'role', 'resource' => 'role', 'action' => 'view', 'name' => 'View Roles', 'description' => 'Can view role definitions'],
            ['code' => 'role.manage', 'module' => 'role', 'resource' => 'role', 'action' => 'manage', 'name' => 'Manage Roles', 'description' => 'Can create, edit, and delete roles'],
            ['code' => 'role.sync_permission', 'module' => 'role', 'resource' => 'permission', 'action' => 'sync', 'name' => 'Sync Role Permissions', 'description' => 'Can assign permissions to roles'],

            // Dashboard & Reports
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'resource' => 'metrics', 'action' => 'view', 'name' => 'View Dashboard', 'description' => 'Can view dashboard metrics'],
            ['code' => 'report.view', 'module' => 'report', 'resource' => 'report', 'action' => 'view', 'name' => 'View Reports', 'description' => 'Can view system reports'],

            // Asset Management
            ['code' => 'asset.view', 'module' => 'asset', 'resource' => 'asset', 'action' => 'view', 'name' => 'View Assets', 'description' => 'Can view asset information'],
            ['code' => 'asset.manage', 'module' => 'asset', 'resource' => 'asset', 'action' => 'manage', 'name' => 'Manage Assets', 'description' => 'Can create, edit, and manage assets'],

            // Performance Management
            ['code' => 'performance.view', 'module' => 'performance', 'resource' => 'review', 'action' => 'view', 'name' => 'View Performance Reviews', 'description' => 'Can view performance reviews'],
            ['code' => 'performance.manage', 'module' => 'performance', 'resource' => 'review', 'action' => 'manage', 'name' => 'Manage Performance Reviews', 'description' => 'Can create and manage performance reviews'],

            // Training Management
            ['code' => 'training.view', 'module' => 'training', 'resource' => 'program', 'action' => 'view', 'name' => 'View Training Programs', 'description' => 'Can view training programs'],
            ['code' => 'training.manage', 'module' => 'training', 'resource' => 'program', 'action' => 'manage', 'name' => 'Manage Training Programs', 'description' => 'Can create and manage training programs'],
        ];

        foreach ($permissions as $permission) {
            DB::table('hcm_permissions')->updateOrInsert(
                ['code' => $permission['code']],
                array_merge($permission, ['uuid' => (string) Str::uuid()])
            );
        }
    }
}
