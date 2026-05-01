<?php

return [
    'groups' => [
        [
            'module' => 'employee',
            'title' => 'Employee Management',
            'description' => 'Master data karyawan, struktur organisasi, dan administrasi HR dasar.',
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
                ['code' => 'employee_bulk_import', 'name' => 'Bulk Import', 'description' => 'Upload massal data employee via template.'],
                ['code' => 'employee_document_center', 'name' => 'Document Center', 'description' => 'Dokumen personal, kontrak, dan arsip employee.'],
                ['code' => 'employee_lifecycle', 'name' => 'Lifecycle Tracking', 'description' => 'Onboarding, promosi, mutasi, resign, sampai termination.'],
            ],
        ],
        [
            'module' => 'attendance',
            'title' => 'Attendance',
            'description' => 'Tracking kehadiran, shift, timesheet, dan koreksi absensi.',
            'features' => [
                ['code' => 'attendance', 'name' => 'Attendance Dashboard', 'description' => 'Dashboard check in/out harian untuk employee.'],
                ['code' => 'attendance_shift_scheduling', 'name' => 'Shift Scheduling', 'description' => 'Atur shift dan jam kerja tim.'],
                ['code' => 'attendance_geo_tracking', 'name' => 'Geo Tracking', 'description' => 'Capture koordinat saat punch in/out.'],
                ['code' => 'attendance_correction_flow', 'name' => 'Correction Workflow', 'description' => 'Ajukan dan approve koreksi absensi.'],
            ],
        ],
        [
            'module' => 'leave',
            'title' => 'Leave Management',
            'description' => 'Pengajuan cuti, saldo, kalender, dan approval flow.',
            'features' => [
                ['code' => 'leave_management', 'name' => 'Leave Requests', 'description' => 'Pengajuan cuti, izin, sakit dari employee.'],
                ['code' => 'leave_approval_flow', 'name' => 'Approval Workflow', 'description' => 'Approval berjenjang manager hingga HR.'],
                ['code' => 'leave_balance_ledger', 'name' => 'Leave Balance Ledger', 'description' => 'Monitoring saldo dan mutasi cuti.'],
                ['code' => 'holiday_calendar', 'name' => 'Holiday Calendar', 'description' => 'Kelola hari libur nasional dan perusahaan.'],
            ],
        ],
        [
            'module' => 'payroll',
            'title' => 'Payroll',
            'description' => 'Komponen gaji, proses payroll, dan distribusi slip gaji.',
            'features' => [
                ['code' => 'payroll', 'name' => 'Payroll Run', 'description' => 'Generate payroll periodik bulanan.'],
                ['code' => 'payroll_components', 'name' => 'Salary Components', 'description' => 'Atur allowance, deduction, dan formula dasar.'],
                ['code' => 'payroll_payslip', 'name' => 'Payslip Publishing', 'description' => 'Publikasi slip gaji digital ke employee.'],
                ['code' => 'payroll_thr', 'name' => 'THR Management', 'description' => 'Perhitungan dan approval THR periodik.'],
            ],
        ],
        [
            'module' => 'performance',
            'title' => 'Performance',
            'description' => 'KPI, goals, penilaian performa, dan pelatihan.',
            'features' => [
                ['code' => 'performance', 'name' => 'Performance Review', 'description' => 'Review performa periodik per employee.'],
                ['code' => 'goal_tracking', 'name' => 'Goal Tracking', 'description' => 'Objective dan KPI tracking lintas periode.'],
                ['code' => 'performance_goal_tracking', 'name' => 'Advanced Goal Tracking', 'description' => 'Goal tracking lanjutan untuk workflow performance.'],
                ['code' => 'performance_calibration', 'name' => 'Calibration Panel', 'description' => 'Panel kalibrasi penilaian tim atau department.'],
                ['code' => 'training', 'name' => 'Training', 'description' => 'Administrasi pelatihan, trainer, dan sesi pembelajaran.'],
            ],
        ],
        [
            'module' => 'assets',
            'title' => 'Asset Management',
            'description' => 'Inventaris aset, attachment, maintenance, dan lifecycle aset.',
            'features' => [
                ['code' => 'asset_management', 'name' => 'Asset Management', 'description' => 'Master aset, assignment, dan stock overview.'],
                ['code' => 'asset_logs', 'name' => 'Asset Logs', 'description' => 'Riwayat perubahan dan perpindahan aset.'],
                ['code' => 'asset_attachments', 'name' => 'Asset Attachments', 'description' => 'Lampiran dokumen, foto, dan bukti aset.'],
                ['code' => 'asset_warranty', 'name' => 'Asset Warranty', 'description' => 'Pemantauan masa garansi dan vendor aset.'],
                ['code' => 'asset_maintenance', 'name' => 'Asset Maintenance', 'description' => 'Jadwal dan histori maintenance aset.'],
                ['code' => 'asset_reporting', 'name' => 'Asset Reporting', 'description' => 'Laporan utilisasi, kondisi, dan kepemilikan aset.'],
                ['code' => 'asset_depreciation', 'name' => 'Asset Depreciation', 'description' => 'Pelacakan nilai buku dan depresiasi aset.'],
            ],
        ],
        [
            'module' => 'platform',
            'title' => 'Platform & Integrations',
            'description' => 'Integrasi, observability, ticketing, dan support operasional.',
            'features' => [
                ['code' => 'api_access', 'name' => 'API Access', 'description' => 'Akses endpoint integrasi public atau internal.'],
                ['code' => 'sso_basic', 'name' => 'SSO Basic', 'description' => 'Single Sign On via provider umum.'],
                ['code' => 'audit_logs', 'name' => 'Audit Logs', 'description' => 'Riwayat aktivitas penting untuk compliance.'],
                ['code' => 'priority_support', 'name' => 'Priority Support', 'description' => 'Jalur support prioritas dengan SLA khusus.'],
                ['code' => 'tickets', 'name' => 'Tickets', 'description' => 'Modul helpdesk internal untuk employee dan admin.'],
            ],
        ],
    ],
];