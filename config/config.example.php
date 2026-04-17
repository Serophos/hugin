<?php
return [
    'app' => [
        'name' => 'Hugin | Open Source Digital Signage',
        'base_url' => '', // Example: http://localhost/hugin/public
        'session_name' => 'hugin_session',
        'debug' => false,
        'locale' => 'en',
        'fallback_locale' => 'en',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'info_display',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'upload' => [
        'max_size_bytes' => 52428800, // 50 MB
    ],
];
