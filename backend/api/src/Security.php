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
        $isHttps = self::isHttpsRequest();

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Content-Security-Policy: default-src \'self\'; base-uri \'self\'; form-action \'self\'; frame-ancestors \'self\'; object-src \'none\'; connect-src \'self\'; style-src \'self\' \'unsafe-inline\'; script-src \'self\'; img-src \'self\' data:;');

        // Avoid browser caching for authenticated/private responses.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // HSTS is only valid over HTTPS.
        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Detects HTTPS behind direct or reverse-proxy setups.
     */
    public static function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        $serverPort = (string) ($_SERVER['SERVER_PORT'] ?? '');
        if ($serverPort === '443') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
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
     * Returns password_hash options with a strong default profile.
     *
     * @return array<string, int>
     */
    public static function passwordHashOptions(): array
    {
        // Argon2id settings target balanced security for local/prod environments.
        return [
            'memory_cost' => 64 * 1024,
            'time_cost' => 4,
            'threads' => 2,
        ];
    }

    /**
     * Hashes password with Argon2id when available, otherwise PASSWORD_DEFAULT.
     */
    public static function hashPassword(string $password): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, self::passwordHashOptions());
        }

        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Validates password and performs optional rehash for legacy hashes.
     *
     * @return array{valid: bool, rehash: string|null}
     */
    public static function verifyPasswordWithRehash(string $password, string $hash): array
    {
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['valid' => false, 'rehash' => null];
        }

        if (defined('PASSWORD_ARGON2ID')) {
            if (password_needs_rehash($hash, PASSWORD_ARGON2ID, self::passwordHashOptions())) {
                return ['valid' => true, 'rehash' => self::hashPassword($password)];
            }
            return ['valid' => true, 'rehash' => null];
        }

        if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return ['valid' => true, 'rehash' => self::hashPassword($password)];
        }

        return ['valid' => true, 'rehash' => null];
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

        $decoded = json_decode($raw, true, 32, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
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

    /**
     * Encrypts sensitive values with AES-256-GCM for secure at-rest storage.
     */
    public static function encryptSecret(string $plainText, string $appSecret): ?string
    {
        if ($plainText === '' || $appSecret === '') {
            return null;
        }

        $key = hash('sha256', $appSecret, true);
        $iv = random_bytes(12);
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($cipherText === false || $tag === '') {
            return null;
        }

        return base64_encode($iv . $tag . $cipherText);
    }

    /**
     * Decrypts values encrypted by encryptSecret.
     */
    public static function decryptSecret(string $encodedCipher, string $appSecret): ?string
    {
        if ($encodedCipher === '' || $appSecret === '') {
            return null;
        }

        $raw = base64_decode($encodedCipher, true);
        if ($raw === false || strlen($raw) < 28) {
            return null;
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipherText = substr($raw, 28);
        $key = hash('sha256', $appSecret, true);

        $plainText = openssl_decrypt(
            $cipherText,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plainText === false ? null : $plainText;
    }
}

