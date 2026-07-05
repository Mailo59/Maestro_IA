<?php

declare(strict_types=1);

return [
    'models' => [
        'tutor_principal' => env('GEMINI_TUTOR_PRINCIPAL_MODEL', 'gemini-3.5-flash'),
        'tutor_respaldo' => env('GEMINI_TUTOR_RESPALDO_MODEL', 'gemini-2.5-flash'),
        'agenda' => env('GEMINI_AGENDA_MODEL', 'gemini-3.1-flash-lite'),
        'calificador' => env('GEMINI_CALIFICADOR_MODEL', 'gemini-2.5-pro'),
    ],
];
