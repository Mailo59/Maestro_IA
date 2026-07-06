<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Permitimos explícitamente cualquier método
    'allowed_methods' => ['*'],

    // Cambia el allowed_origins temporalmente a esto para asegurarnos de que acepte el front
    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // ¡ESTA ES LA CLAVE! Cámbialo obligatoriamente a false
    // Si está en true con allowed_origins en '*', el navegador aborta la petición por seguridad
    'supports_credentials' => false,
];
