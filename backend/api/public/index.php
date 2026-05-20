<?php
declare(strict_types=1);

/**
 * Public front controller.
 */

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = rawurldecode((string) (parse_url($requestUri, PHP_URL_PATH) ?? '/'));
$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

// Reject unexpected HTTP verbs to reduce attack surface.
$allowedMethods = ['GET', 'POST', 'OPTIONS'];
if (!in_array($method, $allowedMethods, true)) {
    http_response_code(405);
    header('Allow: GET, POST, OPTIONS');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Metodo no permitido';
    exit;
}

// CORS preflight is not supported in local private API/dashboard.
if ($method === 'OPTIONS') {
    http_response_code(204);
    header('Allow: GET, POST, OPTIONS');
    exit;
}

// Global payload limit to reduce memory abuse and oversized-input attacks.
if ($contentLength > 200_000) {
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Payload demasiado grande';
    exit;
}

// API JSON endpoints only accept JSON bodies in POST.
if ($method === 'POST' && str_starts_with($path, '/api/')) {
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if ($contentType !== '' && !str_starts_with($contentType, 'application/json')) {
        http_response_code(415);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'message' => 'Content-Type debe ser application/json para API.',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

require_once __DIR__ . '/../src/bootstrap.php';

