<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'auth/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['*'], // 👈 change to * first
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
