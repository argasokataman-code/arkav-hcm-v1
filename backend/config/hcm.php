<?php

return [

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
