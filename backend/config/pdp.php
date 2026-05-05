<?php

return [
    'dpo_name' => env('PDP_DPO_NAME', 'Tim Data Protection ARCAV HCM'),
    'dpo_email' => env('PDP_DPO_EMAIL', 'dpo@arcav.id'),
    'privacy_contact_url' => env('PDP_PRIVACY_CONTACT_URL', env('APP_URL', 'http://localhost').'/privacy-policy'),

    'retention' => [
        // M2: AI chat logs should be removed after 1 year.
        'ai_chat_days' => (int) env('PDP_RETENTION_AI_CHAT_DAYS', 365),
        // M3: Attendance records should be removed after 5 years.
        'attendance_years' => (int) env('PDP_RETENTION_ATTENDANCE_YEARS', 5),
    ],
];
