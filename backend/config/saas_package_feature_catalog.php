<?php

return [
    // Source for add-on classification: 'db' uses `package_addons` table; 'runtime' uses
    // the runtime feature catalog (mvp list vs others). Defaults to 'db'.
    'addon_source' => env('SAAS_ADDON_SOURCE', 'db'),

    'mvp_feature_codes' => [
        'max_employees',
        'employee_management',
        'attendance',
        'leave_management',
        'holiday_calendar',
        'payroll',
        'payroll_components',
        'payroll_thr',
        'notifications',
        'trial_billing_dashboard',
        'tax_governance',
        'bpjs_governance',
    ],

    'groups' => [
        [
            'module' => 'employee',
            'title' => 'Employee Management',
            'description' => 'Fitur inti employee yang aktif dipakai pada flow HCM saat ini.',
            'features' => [
                [
                    'code' => 'max_employees',
                    'name' => 'Maximum Employees',
                    'description' => 'Batasi jumlah employee aktif yang bisa dikelola dalam paket ini.',
                    'requiresLimit' => true,
                    'limitLabel' => 'Jumlah employee',
                    'limitPlaceholder' => 'Contoh: 50',
                    'limitSuffix' => 'org',
                ],
                ['code' => 'employee_management', 'name' => 'Employee Directory', 'description' => 'List, profile, dan pencarian data karyawan.'],
                ['code' => 'employee_document_center', 'name' => 'Document Center', 'description' => 'Dokumen personal, kontrak, dan arsip employee.'],
                ['code' => 'employee_lifecycle', 'name' => 'Lifecycle Tracking', 'description' => 'Onboarding, promosi, mutasi, resign, sampai termination.'],
            ],
        ],
        [
            'module' => 'attendance',
            'title' => 'Attendance',
            'description' => 'Fitur attendance yang dipakai untuk akses absensi dan scheduling.',
            'features' => [
                ['code' => 'attendance', 'name' => 'Attendance Dashboard', 'description' => 'Dashboard check in/out harian untuk employee.'],
                ['code' => 'attendance_shift_scheduling', 'name' => 'Shift Scheduling', 'description' => 'Atur shift dan jam kerja tim.'],
                ['code' => 'attendance_correction', 'name' => 'Attendance Correction', 'description' => 'Manajemen pengajuan koreksi absensi: review, approve, dan dismiss permintaan koreksi dari employee.'],
                ['code' => 'overtime', 'name' => 'Overtime Management', 'description' => 'Manajemen pengajuan overtime: tipe overtime, request, approval, dan kalkulasi.'],
            ],
        ],
        [
            'module' => 'leave',
            'title' => 'Leave Management',
            'description' => 'Fitur leave yang aktif untuk request, approval, dan kalender libur.',
            'features' => [
                ['code' => 'leave_management', 'name' => 'Leave Requests', 'description' => 'Pengajuan cuti, izin, sakit dari employee.'],
                ['code' => 'holiday_calendar', 'name' => 'Holiday Calendar', 'description' => 'Kelola hari libur nasional dan perusahaan.'],
            ],
        ],
        [
            'module' => 'payroll',
            'title' => 'Payroll',
            'description' => 'Fitur payroll yang aktif untuk proses gaji dan THR.',
            'features' => [
                ['code' => 'payroll', 'name' => 'Payroll Run', 'description' => 'Generate payroll periodik bulanan.'],
                ['code' => 'payroll_components', 'name' => 'Compensation Components', 'description' => 'Kelola komponen kompensasi seperti allowance dan deduction payroll.'],
                ['code' => 'payroll_thr', 'name' => 'THR Management', 'description' => 'Perhitungan dan approval THR periodik.'],
            ],
        ],
        [
            'module' => 'performance',
            'title' => 'Performance',
            'description' => 'Fitur performance dan goal yang saat ini dipakai di modul HCM.',
            'features' => [
                ['code' => 'performance', 'name' => 'Performance Review', 'description' => 'Review performa periodik per employee.'],
                ['code' => 'goal_tracking', 'name' => 'Goal Tracking', 'description' => 'Objective dan KPI tracking lintas periode.'],
            ],
        ],
        [
            'module' => 'training',
            'title' => 'Training',
            'description' => 'Administrasi pelatihan, trainer, dan sesi pembelajaran SDM.',
            'features' => [
                ['code' => 'training', 'name' => 'Training', 'description' => 'Administrasi pelatihan, trainer, dan sesi pembelajaran.'],
            ],
        ],
        [
            'module' => 'assets',
            'title' => 'Asset Management',
            'description' => 'Fitur inti asset management untuk inventaris perusahaan.',
            'features' => [
                ['code' => 'asset_management', 'name' => 'Asset Management', 'description' => 'Master aset, assignment, dan stock overview.'],
            ],
        ],
        [
            'module' => 'platform',
            'title' => 'Platform',
            'description' => 'Fitur platform aktif untuk operasional internal.',
            'features' => [
                ['code' => 'notifications', 'name' => 'Notifications', 'description' => 'Inbox notifikasi, preferensi channel, dan observability notifikasi tenant.'],
                ['code' => 'trial_billing_dashboard', 'name' => 'Trial Billing Dashboard', 'description' => 'Monitoring trial, invoice lifecycle, dan kesehatan billing tenant.'],
                ['code' => 'tax_governance', 'name' => 'Tax Governance', 'description' => 'Governance pajak dan compliance payroll/billing lintas siklus tenant.'],
                ['code' => 'bpjs_governance', 'name' => 'BPJS Governance', 'description' => 'Manajemen kebijakan BPJS Kesehatan dan Ketenagakerjaan, iuran, dan keanggotaan karyawan.'],
                ['code' => 'ai_assistant', 'name' => 'AI Assistant', 'description' => 'Chat assistant berbasis AI untuk query data HCM.'],
            ],
        ],
        [
            'module' => 'tickets',
            'title' => 'Tickets',
            'description' => 'Modul helpdesk internal untuk employee dan admin.',
            'features' => [
                ['code' => 'tickets', 'name' => 'Tickets', 'description' => 'Modul helpdesk internal untuk employee dan admin.'],
            ],
        ],
    ],
];