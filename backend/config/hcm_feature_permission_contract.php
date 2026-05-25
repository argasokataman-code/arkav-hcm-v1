<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HCM Feature <-> Permission Contract
    |--------------------------------------------------------------------------
    | Single source of truth untuk entitlement paket vs permission RBAC tenant.
    | Rule ini dipakai untuk memfilter permission catalog/sync agar tenant
    | tidak bisa assign permission yang fiturnya tidak ada di paket aktif.
    */

    'permission_rules' => [
        // Tickets
        'ticket.view' => ['any_of' => ['tickets']],
        'ticket.create' => ['any_of' => ['tickets']],
        'ticket.update' => ['any_of' => ['tickets']],
        'ticket.assign' => ['any_of' => ['tickets']],
        'ticket.category.manage' => ['any_of' => ['tickets']],

        // Training
        'training.view' => ['any_of' => ['training']],
        'training.manage' => ['any_of' => ['training']],
        'trainer.view' => ['any_of' => ['training']],
        'trainer.manage' => ['any_of' => ['training']],

        // Payroll
        'payroll.view' => ['any_of' => ['payroll']],
        'payroll.create' => ['any_of' => ['payroll']],
        'payroll.update' => ['any_of' => ['payroll']],
        'payroll.run' => ['any_of' => ['payroll']],
        'payroll.finalize' => ['any_of' => ['payroll']],
        'payroll.disburse' => ['any_of' => ['payroll']],
        'payroll.item.manage' => ['any_of' => ['payroll']],
        'payroll.thr.manage' => ['any_of' => ['payroll', 'payroll_thr']],
        'payroll.pkwt.manage' => ['any_of' => ['payroll']],

        // Performance
        'performance.view' => ['any_of' => ['performance']],
        'performance.manage' => ['any_of' => ['performance']],
        'goal.view' => ['any_of' => ['goal_tracking', 'performance_goal_tracking']],
        'goal.manage' => ['any_of' => ['goal_tracking', 'performance_goal_tracking']],

        // Core HCM modules commonly bundled in package features
        'employee.view' => ['any_of' => ['employee_management']],
        'employee.create' => ['any_of' => ['employee_management']],
        'employee.update' => ['any_of' => ['employee_management']],
        'employee.delete' => ['any_of' => ['employee_management']],
        'employee.export' => ['any_of' => ['employee_management']],

        'attendance.view' => ['any_of' => ['attendance']],
        'attendance.create' => ['any_of' => ['attendance']],
        'attendance.update' => ['any_of' => ['attendance']],
        'attendance.admin' => ['any_of' => ['attendance']],
        'attendance.correction' => ['any_of' => ['attendance_correction']],
        'timesheet.view' => ['any_of' => ['attendance']],
        'schedule.view' => ['any_of' => ['attendance', 'attendance_shift_scheduling']],
        'schedule.manage' => ['any_of' => ['attendance', 'attendance_shift_scheduling']],

        'leave.view' => ['any_of' => ['leave_management']],
        'leave.create' => ['any_of' => ['leave_management']],
        'leave.update' => ['any_of' => ['leave_management']],
        'leave.approve' => ['any_of' => ['leave_management', 'leave_approval_flow']],
        'leave.reject' => ['any_of' => ['leave_management', 'leave_approval_flow']],
        'leave.settings' => ['any_of' => ['leave_management']],
        'leave.type' => ['any_of' => ['leave_management']],

        'holiday.view' => ['any_of' => ['holiday_calendar']],
        'holiday.create' => ['any_of' => ['holiday_calendar']],
        'holiday.update' => ['any_of' => ['holiday_calendar']],
        'holiday.sync' => ['any_of' => ['holiday_calendar']],

        'promotion.view' => ['any_of' => ['employee_lifecycle']],
        'promotion.manage' => ['any_of' => ['employee_lifecycle']],
        'resignation.view' => ['any_of' => ['employee_lifecycle']],
        'resignation.manage' => ['any_of' => ['employee_lifecycle']],
        'termination.view' => ['any_of' => ['employee_lifecycle']],
        'termination.manage' => ['any_of' => ['employee_lifecycle']],

        // Asset Management
        'asset.view' => ['any_of' => ['asset_management']],
        'asset.manage' => ['any_of' => ['asset_management']],
    ],

    // Permission yang tidak di-gate oleh package feature.
    'always_allowed_permissions' => [
        'dashboard.view',

        // User management / tenant governance
        'user.view',
        'user.create',
        'user.update',
        'user.assign_role',
        'role.view',
        'role.create',
        'role.update',
        'role.delete',
        'role.sync_permission',

        // Organization / policy admin
        'department.view',
        'department.manage',
        'designation.view',
        'designation.manage',
        'policy.view',
        'policy.manage',
        'team.manage',
        'team.lead',

        // Report & system surface
        'report.view',
        'report.export',
        'settings.view',
        'settings.manage',
        'cron.manage',

        // Overtime currently not represented as package feature in catalog.
        'overtime.view',
        'overtime.create',
        'overtime.approve',
        'overtime.type.manage',
    ],
];
