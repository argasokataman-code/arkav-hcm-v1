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
        'login',
        'register',
        'signout',
        'api-docs',
        'api-docs/openapi.yaml',
    ],

    'public_prefixes' => [
        // Contoh dev: 'dev-theme-preview'
    ],
];
