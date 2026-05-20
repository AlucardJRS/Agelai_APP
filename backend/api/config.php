<?php
declare(strict_types=1);

/**
 * Centralized app configuration for local development.
 * For production, use environment variables and secrets management.
 */
return [
    'app_name' => 'Club Agelai',
    'timezone' => 'Europe/Madrid',
    'base_url' => 'http://127.0.0.1:8000',
    'db' => [
        'host' => getenv('AGELAI_DB_HOST') !== false ? (string) getenv('AGELAI_DB_HOST') : '127.0.0.1',
        'port' => getenv('AGELAI_DB_PORT') !== false ? (int) getenv('AGELAI_DB_PORT') : 3306,
        'database' => getenv('AGELAI_DB_NAME') !== false ? (string) getenv('AGELAI_DB_NAME') : 'agelai_dietas',
        'username' => getenv('AGELAI_DB_USER') !== false ? (string) getenv('AGELAI_DB_USER') : 'root',
        'password' => getenv('AGELAI_DB_PASS') !== false ? (string) getenv('AGELAI_DB_PASS') : '',
        'charset' => 'utf8mb4',
    ],
    'admin_seed' => [
        'username' => 'admin',
        'password' => 'Admin12345!',
    ],
    'token_ttl_hours' => 24,
    'reservation_cancellation_hours' => 2,
    'allowed_modules' => [
        'martial_arts' => 'Artes Marciales',
        'gym' => 'Musculacion y Gym',
        'diets' => 'Dietas',
        'directed_classes' => 'Clases Dirigidas',
    ],
];
