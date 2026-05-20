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
    'app_secret_key' => getenv('AGELAI_APP_SECRET') !== false
        ? (string) getenv('AGELAI_APP_SECRET')
        : 'change-this-local-secret-before-production',
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
    'require_admin_approval_for_all_reservations' => true,
    'payment_methods' => [
        'cash' => 'Efectivo',
        'bizum' => 'Bizum',
        'card' => 'Tarjeta',
    ],
    'locations' => [
        'cala_dor' => [
            'name' => "Cala d'Or (rotonda Farash)",
            'is_primary' => true,
        ],
        'cala_egos' => [
            'name' => 'Cala Egos (delante del SYP)',
            'is_primary' => false,
        ],
    ],
    'google_oauth' => [
        'client_id' => getenv('AGELAI_GOOGLE_CLIENT_ID') !== false ? (string) getenv('AGELAI_GOOGLE_CLIENT_ID') : '',
        'client_secret' => getenv('AGELAI_GOOGLE_CLIENT_SECRET') !== false ? (string) getenv('AGELAI_GOOGLE_CLIENT_SECRET') : '',
        'redirect_uri' => getenv('AGELAI_GOOGLE_REDIRECT_URI') !== false
            ? (string) getenv('AGELAI_GOOGLE_REDIRECT_URI')
            : 'http://127.0.0.1:8000/oauth/google/callback',
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/calendar.events',
        ],
    ],
    'smtp' => [
        'host' => getenv('AGELAI_SMTP_HOST') !== false ? (string) getenv('AGELAI_SMTP_HOST') : '',
        'port' => getenv('AGELAI_SMTP_PORT') !== false ? (int) getenv('AGELAI_SMTP_PORT') : 587,
        'secure' => getenv('AGELAI_SMTP_SECURE') !== false ? (string) getenv('AGELAI_SMTP_SECURE') : 'tls',
        'username' => getenv('AGELAI_SMTP_USER') !== false ? (string) getenv('AGELAI_SMTP_USER') : '',
        'password' => getenv('AGELAI_SMTP_PASS') !== false ? (string) getenv('AGELAI_SMTP_PASS') : '',
        'from_email' => getenv('AGELAI_SMTP_FROM_EMAIL') !== false ? (string) getenv('AGELAI_SMTP_FROM_EMAIL') : '',
        'from_name' => getenv('AGELAI_SMTP_FROM_NAME') !== false ? (string) getenv('AGELAI_SMTP_FROM_NAME') : 'Club Agelai',
        'timeout_seconds' => 15,
    ],
    'allowed_modules' => [
        'martial_arts' => 'Artes Marciales',
        'gym' => 'Musculacion y Gym',
        'diets' => 'Dietas',
        'directed_classes' => 'Clases Dirigidas',
    ],
];
