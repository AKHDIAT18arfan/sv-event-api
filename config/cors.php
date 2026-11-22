<?php

/*
 * Salin ke config/cors.php (atau jalankan "php artisan config:publish cors" lalu samakan isinya).
 * Front-end yang berbeda alamat dengan API harus didaftarkan di sini, kalau tidak browser
 * akan memblokir request-nya.
 */
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('FRONTEND_URL', 'http://localhost:5173')))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'], // supaya nama file ekspor Excel terbaca oleh front-end
    'max_age' => 0,
    'supports_credentials' => false, // token Bearer, bukan cookie
];
