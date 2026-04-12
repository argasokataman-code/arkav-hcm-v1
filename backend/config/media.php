<?php

return [

    'default_disk' => env('MEDIA_DISK', 'public'),

    'policy_attachment' => [
        'max_kb' => (int) env('MEDIA_POLICY_MAX_KB', 12_288),
        'image_max_width' => (int) env('MEDIA_POLICY_IMAGE_MAX_W', 1920),
        'image_max_height' => (int) env('MEDIA_POLICY_IMAGE_MAX_H', 1920),
        'jpeg_quality' => (int) env('MEDIA_POLICY_JPEG_QUALITY', 82),
    ],

    'avatar' => [
        'max_kb' => (int) env('MEDIA_AVATAR_MAX_KB', 2048),
        'image_max_width' => (int) env('MEDIA_AVATAR_MAX_W', 512),
        'image_max_height' => (int) env('MEDIA_AVATAR_MAX_H', 512),
        'jpeg_quality' => (int) env('MEDIA_AVATAR_JPEG_QUALITY', 85),
    ],

];
