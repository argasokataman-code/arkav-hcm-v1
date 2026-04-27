<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development Super User
    |--------------------------------------------------------------------------
    |
    | Akun ini dipakai sebagai super user default untuk environment development
    | dan testing. Seeder akan memastikan akun ini selalu tersedia.
    |
    */
    'admin_email' => env('HCM_ADMIN_EMAIL', 'qa.login@example.com'),
    'admin_password' => env('HCM_ADMIN_PASSWORD', 'StrongPass1'),
    'secondary_admin_email' => env('HCM_SECONDARY_ADMIN_EMAIL', 'qa.hcm@example.com'),
    'secondary_admin_password' => env('HCM_SECONDARY_ADMIN_PASSWORD', 'StrongPass1'),

    /*
    |--------------------------------------------------------------------------
    | Global Admin Default Tenant Context
    |--------------------------------------------------------------------------
    |
    | Saat global super admin login tanpa header tenant eksplisit, resolver
    | memilih membership aktif berdasarkan prioritas company code ini.
    |
    */
    'super_admin_default_company_code' => env('HCM_SUPER_ADMIN_DEFAULT_COMPANY_CODE', 'default_company'),

    /*
    |--------------------------------------------------------------------------
    | Alamat organisasi (slip PDF / surat resmi)
    |--------------------------------------------------------------------------
    */
    'organization_address' => env('HCM_ORGANIZATION_ADDRESS', ''),

    /*
    |--------------------------------------------------------------------------
    | THR disbursement (payment gateway)
    |--------------------------------------------------------------------------
    |
    | driver: "stub" simulates Xendit/Midtrans; swap to "xendit" / "midtrans" when wired.
    | fail_user_ids: user IDs that always fail in stub (for QA).
    |
    */
    'thr_disbursement_driver' => env('HCM_THR_DISBURSEMENT_DRIVER', 'stub'),

    'thr_disbursement_fail_user_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('HCM_THR_DISBURSEMENT_FAIL_USER_IDS', ''))
    ))),

    'export_reconciliation' => [
        'enabled' => env('HCM_EXPORT_RECON_ENABLED', true),
        'ttl_minutes' => (int) env('HCM_EXPORT_RECON_TTL_MINUTES', 30),
        'strict_checksum' => env('HCM_EXPORT_RECON_STRICT_CHECKSUM', false),
        'enforce' => [
            'payroll_run' => [
                'finalize' => env('HCM_EXPORT_RECON_ENFORCE_PAYROLL_FINALIZE', false),
                'disburse' => env('HCM_EXPORT_RECON_ENFORCE_PAYROLL_DISBURSE', false),
            ],
            'invoice' => [
                'mark_paid' => env('HCM_EXPORT_RECON_ENFORCE_INVOICE_MARK_PAID', false),
            ],
            'payment' => [
                'verify' => env('HCM_EXPORT_RECON_ENFORCE_PAYMENT_VERIFY', false),
            ],
            'thr_batch' => [
                'disburse' => env('HCM_EXPORT_RECON_ENFORCE_THR_DISBURSE', false),
                'post_payroll' => env('HCM_EXPORT_RECON_ENFORCE_THR_POST_PAYROLL', false),
            ],
            'pkwt_compensation' => [
                'post_payroll' => env('HCM_EXPORT_RECON_ENFORCE_PKWT_POST_PAYROLL', false),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payroll — leave & holiday integration (H3)
    |--------------------------------------------------------------------------
    |
    | Bila aktif, PayrollDraftBuilder menambahkan:
    |   - line deduction `potongan_cuti_unpaid` (cuti approved dengan tipe
    |     unpaid yang jatuh di bulan periode)
    |   - line addition `tunjangan_kerja_libur` (attendance_records yang
    |     bekerja di tanggal libur; multiplier default 2x daily rate)
    |
    | Default `false` agar tenant existing tidak berubah perilakunya. Per-tenant
    | override lewat `company_settings` (key: `payroll.leave_integration_enabled`).
    */
    'payroll' => [
        'leave_integration_enabled' => env('HCM_PAYROLL_LEAVE_INTEGRATION', false),
        'holiday_work_multiplier' => (float) env('HCM_PAYROLL_HOLIDAY_WORK_MULTIPLIER', 2.0),
    ],

    'employment_statuses' => ['probation', 'active', 'resigned', 'terminated', 'inactive'],

    'contract_types' => ['contract', 'permanent'],

    'contract_statuses' => ['active', 'ended', 'terminated'],

    'salary_types' => ['monthly', 'daily', 'hourly'],

    'marital_statuses' => ['single', 'married', 'divorced', 'widowed'],

    'religions' => ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'],

    'tax_statuses' => ['TK0', 'TK1', 'TK2', 'TK3', 'K0', 'K1', 'K2', 'K3'],

    'allowed_bank_names' => [
        'BCA',
        'Bank Mandiri',
        'BNI',
        'BRI',
        'BTN',
        'CIMB Niaga',
        'Permata Bank',
        'Danamon',
        'BSI',
        'OCBC NISP',
        'Panin Bank',
        'Maybank Indonesia',
        'Bank Mega',
        'Bank Sinarmas',
        'Jenius / BTPN',
        'SeaBank',
        'Bank Jago',
    ],

];
