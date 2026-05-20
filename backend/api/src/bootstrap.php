<?php
declare(strict_types=1);

/**
 * Application bootstrap for local environment.
 */

// Security-focused session settings.
session_name('agelai_admin_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Strict',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Optional local secrets file loader for development convenience.
$localEnvFile = __DIR__ . '/../.env.local';
if (is_file($localEnvFile) && is_readable($localEnvFile)) {
    $lines = file($localEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            if ($key === '' || !preg_match('/^[A-Z0-9_]+$/', $key)) {
                continue;
            }
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, '\'') && str_ends_with($value, '\''))
            ) {
                $value = substr($value, 1, -1);
            }
            $value = str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $value);

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

$config = require __DIR__ . '/../config.php';
date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Integrations.php';
require_once __DIR__ . '/App.php';

$database = new Database((array) ($config['db'] ?? []));
$database->ensureSchema($config);

$app = new App($database->pdo(), $config);
$app->handle();
