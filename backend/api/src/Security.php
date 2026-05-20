<?php
declare(strict_types=1);

/**
 * Security helper with defensive defaults for local and production-ready code.
 */
final class Security
{
    /**
     * Sets baseline HTTP security headers.
     */
    public static function addSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Content-Security-Policy: default-src \'self\'; style-src \'self\' \'unsafe-inline\'; script-src \'self\'; img-src \'self\' data:;');
    }

    /**
     * HTML escaping helper to prevent reflected/stored XSS in templates.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Returns a CSRF token from session, creating one when missing.
     */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }

    /**
     * Constant-time CSRF token comparison for unsafe requests.
     */
    public static function verifyCsrf(?string $token): bool
    {
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        $inputToken = (string) ($token ?? '');

        return $sessionToken !== '' && hash_equals($sessionToken, $inputToken);
    }

    /**
     * Reads JSON body safely and limits payload size.
     *
     * @return array<string, mixed>
     */
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        // Defensive payload limit for abuse resistance.
        if (strlen($raw) > 100_000) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Returns a validated module code or null.
     */
    public static function normalizeModuleCode(mixed $moduleCode, array $allowedModules): ?string
    {
        if (!is_string($moduleCode)) {
            return null;
        }

        $moduleCode = trim($moduleCode);
        if ($moduleCode === '' || !array_key_exists($moduleCode, $allowedModules)) {
            return null;
        }

        return $moduleCode;
    }
}

