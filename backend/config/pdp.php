<?php

return [
    'dpo_name' => env('PDP_DPO_NAME', 'Tim Data Protection ARCAV HCM'),
    'dpo_email' => env('PDP_DPO_EMAIL', 'dpo@arcav.id'),
    // URL halaman kebijakan privasi. Kosongkan agar otomatis mengikuti domain aktif
    // (url('/privacy-policy')). Override via PDP_PRIVACY_CONTACT_URL di .env jika
    // butuh URL khusus (misalnya domain marketing terpisah).
    'privacy_contact_url' => env('PDP_PRIVACY_CONTACT_URL'),

    'retention' => [
        // M2: AI chat logs should be removed after 1 year.
        'ai_chat_days' => (int) env('PDP_RETENTION_AI_CHAT_DAYS', 365),
        // M3: Attendance records should be removed after 5 years.
        'attendance_years' => (int) env('PDP_RETENTION_ATTENDANCE_YEARS', 5),
    ],

    // M8: Session timeout for sensitive operations (re-auth required after idle).
    'session_timeout_minutes' => (int) env('PDP_SESSION_TIMEOUT_MINUTES', 30),

    // L4: Payslip encryption before email delivery.
    'payslip_encryption_enabled' => (bool) env('PDP_PAYSLIP_ENCRYPTION_ENABLED', true),
];
