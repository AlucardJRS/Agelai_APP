<?php
declare(strict_types=1);

/**
 * External integrations: Google OAuth/Calendar and SMTP mail.
 */
final class Integrations
{
    public function __construct(private readonly array $config)
    {
    }

    public function googleOauthConfigured(): bool
    {
        $google = (array) ($this->config['google_oauth'] ?? []);
        return $this->requiredString($google, 'client_id') !== ''
            && $this->requiredString($google, 'client_secret') !== ''
            && $this->requiredString($google, 'redirect_uri') !== '';
    }

    /**
     * Builds Google OAuth consent URL.
     *
     * @param array<int, string>|null $scopesOverride
     */
    public function buildGoogleAuthUrl(
        string $state,
        ?array $scopesOverride = null,
        string $prompt = 'consent'
    ): string
    {
        $google = (array) ($this->config['google_oauth'] ?? []);
        $scopes = $scopesOverride === null ? (array) ($google['scopes'] ?? []) : $scopesOverride;

        $params = [
            'client_id' => $this->requiredString($google, 'client_id'),
            'redirect_uri' => $this->requiredString($google, 'redirect_uri'),
            'response_type' => 'code',
            'scope' => implode(' ', array_map('strval', $scopes)),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => $prompt,
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchanges Google OAuth authorization code for token payload.
     *
     * @return array<string, mixed>
     */
    public function exchangeGoogleAuthCode(string $code): array
    {
        $google = (array) ($this->config['google_oauth'] ?? []);
        $payload = [
            'code' => $code,
            'client_id' => $this->requiredString($google, 'client_id'),
            'client_secret' => $this->requiredString($google, 'client_secret'),
            'redirect_uri' => $this->requiredString($google, 'redirect_uri'),
            'grant_type' => 'authorization_code',
        ];

        return $this->httpPostFormJson('https://oauth2.googleapis.com/token', $payload);
    }

    /**
     * Refreshes Google access token with stored refresh token.
     *
     * @return array<string, mixed>
     */
    public function refreshGoogleAccessToken(string $refreshToken): array
    {
        $google = (array) ($this->config['google_oauth'] ?? []);
        $payload = [
            'refresh_token' => $refreshToken,
            'client_id' => $this->requiredString($google, 'client_id'),
            'client_secret' => $this->requiredString($google, 'client_secret'),
            'grant_type' => 'refresh_token',
        ];

        return $this->httpPostFormJson('https://oauth2.googleapis.com/token', $payload);
    }

    public function fetchGoogleUserEmail(string $accessToken): ?string
    {
        $profile = $this->fetchGoogleUserProfile($accessToken);
        if ($profile === null) {
            return null;
        }

        $email = (string) ($profile['email'] ?? '');
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchGoogleUserProfile(string $accessToken): ?array
    {
        $json = $this->httpGetJson(
            'https://www.googleapis.com/oauth2/v2/userinfo',
            [
                'Authorization: Bearer ' . $accessToken,
            ]
        );

        return is_array($json) ? $json : null;
    }

    /**
     * @param array<string, mixed> $eventData
     */
    public function createGoogleCalendarEvent(string $accessToken, array $eventData): ?string
    {
        $json = $this->httpPostJson(
            'https://www.googleapis.com/calendar/v3/calendars/primary/events',
            $eventData,
            [
                'Authorization: Bearer ' . $accessToken,
            ]
        );

        $eventId = (string) ($json['id'] ?? '');
        return $eventId !== '' ? $eventId : null;
    }

    public function smtpConfigured(): bool
    {
        $smtp = (array) ($this->config['smtp'] ?? []);
        return $this->requiredString($smtp, 'host') !== ''
            && $this->requiredString($smtp, 'from_email') !== '';
    }

    /**
     * Sends plaintext mail through SMTP.
     *
     * @return array{ok:bool,error:string}
     */
    public function sendSmtpMail(string $toEmail, string $subject, string $textBody): array
    {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Email destino invalido.'];
        }

        $smtp = (array) ($this->config['smtp'] ?? []);
        $host = $this->requiredString($smtp, 'host');
        $port = (int) ($smtp['port'] ?? 587);
        $secure = strtolower((string) ($smtp['secure'] ?? 'tls'));
        $username = (string) ($smtp['username'] ?? '');
        $password = (string) ($smtp['password'] ?? '');
        $fromEmail = (string) ($smtp['from_email'] ?? '');
        $fromName = (string) ($smtp['from_name'] ?? 'Club Agelai');
        $timeout = max(5, (int) ($smtp['timeout_seconds'] ?? 15));

        if ($host === '' || $fromEmail === '') {
            return ['ok' => false, 'error' => 'SMTP no configurado.'];
        }

        $transportHost = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $socket = @stream_socket_client(
            $transportHost . ':' . $port,
            $errorCode,
            $errorMessage,
            $timeout
        );

        if (!is_resource($socket)) {
            return ['ok' => false, 'error' => 'No se pudo conectar SMTP: ' . $errorMessage . ' (' . $errorCode . ')'];
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->smtpReadExpect($socket, [220]);
            $this->smtpWrite($socket, 'EHLO clubagelai.local');
            $this->smtpReadExpect($socket, [250]);

            if ($secure === 'tls') {
                $this->smtpWrite($socket, 'STARTTLS');
                $this->smtpReadExpect($socket, [220]);
                $tlsOk = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($tlsOk !== true) {
                    return ['ok' => false, 'error' => 'No se pudo activar TLS en SMTP.'];
                }
                $this->smtpWrite($socket, 'EHLO clubagelai.local');
                $this->smtpReadExpect($socket, [250]);
            }

            if ($username !== '') {
                $this->smtpWrite($socket, 'AUTH LOGIN');
                $this->smtpReadExpect($socket, [334]);
                $this->smtpWrite($socket, base64_encode($username));
                $this->smtpReadExpect($socket, [334]);
                $this->smtpWrite($socket, base64_encode($password));
                $this->smtpReadExpect($socket, [235]);
            }

            $this->smtpWrite($socket, 'MAIL FROM:<' . $fromEmail . '>');
            $this->smtpReadExpect($socket, [250]);
            $this->smtpWrite($socket, 'RCPT TO:<' . $toEmail . '>');
            $this->smtpReadExpect($socket, [250, 251]);
            $this->smtpWrite($socket, 'DATA');
            $this->smtpReadExpect($socket, [354]);

            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $headers = [
                'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
                'From: ' . $this->formatMailbox($fromEmail, $fromName),
                'To: <' . $toEmail . '>',
                'Subject: ' . $encodedSubject,
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: 8bit',
            ];

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $this->smtpDotEscape($textBody) . "\r\n.";
            $this->smtpWrite($socket, $data);
            $this->smtpReadExpect($socket, [250]);

            $this->smtpWrite($socket, 'QUIT');
            $this->smtpReadExpect($socket, [221]);
        } catch (\Throwable $exception) {
            fclose($socket);
            return ['ok' => false, 'error' => 'SMTP error: ' . $exception->getMessage()];
        }

        fclose($socket);
        return ['ok' => true, 'error' => ''];
    }

    /**
     * @param array<string, string> $formData
     * @return array<string, mixed>
     */
    private function httpPostFormJson(string $url, array $formData): array
    {
        $body = http_build_query($formData);
        return $this->httpRequestJson($url, 'POST', ['Content-Type: application/x-www-form-urlencoded'], $body);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpPostJson(string $url, array $payload, array $headers = []): array
    {
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonPayload === false) {
            throw new \RuntimeException('No se pudo serializar payload JSON.');
        }

        $finalHeaders = array_merge(['Content-Type: application/json'], $headers);
        return $this->httpRequestJson($url, 'POST', $finalHeaders, $jsonPayload);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpGetJson(string $url, array $headers = []): array
    {
        return $this->httpRequestJson($url, 'GET', $headers, null);
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, mixed>
     */
    private function httpRequestJson(string $url, string $method, array $headers, ?string $body): array
    {
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('No se pudo inicializar cURL.');
        }

        $timeout = max(5, (int) (((array) ($this->config['smtp'] ?? []))['timeout_seconds'] ?? 15));

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $rawResponse = curl_exec($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            throw new \RuntimeException('Error HTTP externo: ' . $curlError);
        }

        $decoded = json_decode((string) $rawResponse, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Respuesta externa invalida.');
        }

        if ($statusCode >= 400) {
            $message = (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'Error externo');
            throw new \RuntimeException('HTTP ' . $statusCode . ': ' . $message);
        }

        return $decoded;
    }

    /**
     * @param resource $socket
     */
    private function smtpWrite($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    /**
     * @param resource $socket
     * @param array<int, int> $allowedCodes
     */
    private function smtpReadExpect($socket, array $allowedCodes): void
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '' || !preg_match('/^(\d{3})/m', $response, $matches)) {
            throw new \RuntimeException('Respuesta SMTP vacia o invalida.');
        }

        $code = (int) $matches[1];
        if (!in_array($code, $allowedCodes, true)) {
            throw new \RuntimeException('SMTP codigo ' . $code . ': ' . trim($response));
        }
    }

    private function formatMailbox(string $email, string $name): string
    {
        $encodedName = '=?UTF-8?B?' . base64_encode($name) . '?=';
        return $encodedName . ' <' . $email . '>';
    }

    private function smtpDotEscape(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $message);
        $normalized = preg_replace('/^\./m', '..', $normalized ?? '') ?? '';
        return str_replace("\n", "\r\n", $normalized);
    }

    /**
     * @param array<string, mixed> $source
     */
    private function requiredString(array $source, string $key): string
    {
        return isset($source[$key]) ? trim((string) $source[$key]) : '';
    }
}
