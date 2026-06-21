<?php

/**
 * Keamanan halaman web (GET/HEAD) — satu mode saja.
 *
 * Aturan: seluruh path web wajib auth (cookie API atau sesi web legacy)
 * KECUALI yang tercantum di public_paths / public_prefixes.
 *
 * Untuk membuka path tema sementara bagi tamu (hanya dev): tambahkan ke public_prefixes,
 * jangan menambah route tanpa mempertimbangkan guard ini.
 */
return [
    /**
     * Path publik (tanpa leading slash). '' = halaman beranda '/'.
     */
    'public_paths' => [
        '',
        'up',
        'landing',
        'trial',
        'login',
        'forgot-password',
        'forgot-password-2',
        'forgot-password-3',
        'reset-password',
        'reset-password-2',
        'reset-password-3',
        'lock-screen',
        'register',
        'register-2',
        'register-3',
        'signout',
        'api-token',
        'api-docs',
        'api-docs/openapi.yaml',
        'privacy-policy',
        'terms-condition',
    ],

    'public_prefixes' => [
        // Allow tokenized reset URL: /reset-password/{token}
        'reset-password',
        // Contoh dev: 'dev-theme-preview'
    ],
];
