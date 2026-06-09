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
    'monitoring' => [
        'enabled' => false,
        'api_token' => 'put_a_secure_random_token_here',
        'online_threshold_seconds' => 180,
        'stale_threshold_seconds' => 1800,
    ],
    'accessibility' => [
        'contact_email' => '',
        'feedback_url' => '',
        'enforcement_url' => '',
        'visual_mode' => 'default', // default, high_contrast, system
        'focus_style' => 'standard', // standard, strong
        'motion' => 'system', // system, reduced
    ],
];
