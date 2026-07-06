<?php

declare(strict_types=1);

$allowedOrigins = array_values(array_filter(array_map(
    static fn (string $origin): string => trim($origin),
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
)));

if (env('FRONTEND_URL')) {
    $allowedOrigins[] = rtrim((string) env('FRONTEND_URL'), '/');
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins !== []
        ? array_values(array_unique([
            ...$allowedOrigins,
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ]))
        : [
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
