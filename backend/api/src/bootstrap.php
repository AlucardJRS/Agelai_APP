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

$config = require __DIR__ . '/../config.php';
date_default_timezone_set((string) ($config['timezone'] ?? 'UTC'));

require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/App.php';

$database = new Database((array) ($config['db'] ?? []));
$database->ensureSchema($config);

$app = new App($database->pdo(), $config);
$app->handle();
