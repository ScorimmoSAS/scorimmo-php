<?php

namespace Scorimmo\Client;

use DateTimeImmutable;
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;

class ScorimmoClient
{
    private ?string $token = null;
    private ?DateTimeImmutable $tokenExpiresAt = null;

    public readonly LeadsResource $leads;

    /**
     * @param int $timeout        Seconds before a request times out (default 10)
     * @param int $maxRetries     Retries on network error or 5xx (default 2)
     */
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $baseUrl = 'https://pro.scorimmo.com',
        private readonly int $timeout = 10,
        private readonly int $maxRetries = 2,
    ) {
        $this->leads = new LeadsResource($this);
    }

    /**
     * Returns a valid JWT token, fetching a new one if expired or not yet set.
     */
    public function getToken(): string
    {
        if ($this->token && $this->tokenExpiresAt > new DateTimeImmutable()) {
            return $this->token;
        }

        $response = $this->rawRequest('POST', '/api/login_check', [
            'username' => $this->username,
            'password' => $this->password,
        ], authenticate: false);

        if (!isset($response['token'])) {
            throw new ScorimmoAuthException('Authentication failed: no token in response');
        }

        $this->token = $response['token'];
        // Expire 60 seconds early to avoid edge cases
        $expiresAt = is_numeric($response['token_expirate_at'])
            ? new DateTimeImmutable('@' . $response['token_expirate_at'])
            : new DateTimeImmutable($response['token_expirate_at']);
        $this->tokenExpiresAt = $expiresAt->modify('-60 seconds');

        return $this->token;
    }

    /**
     * Authenticated JSON request.
     * On a 401 the token cache is cleared and the request is retried once with a fresh token.
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, mixed $body = null): array
    {
        try {
            return $this->rawRequest($method, $path, $body, authenticate: true);
        } catch (ScorimmoApiException $e) {
            if ($e->statusCode !== 401) {
                throw $e;
            }

            // Token expired server-side: invalidate cache and retry once with a fresh token
            $this->token          = null;
            $this->tokenExpiresAt = null;

            return $this->rawRequest($method, $path, $body, authenticate: true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function rawRequest(string $method, string $path, mixed $body = null, bool $authenticate = true): array
    {
        $url = rtrim($this->baseUrl, '/') . $path;

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($authenticate) {
            $headers[] = 'Authorization: Bearer ' . $this->getToken();
        }

        $attempt = 0;
        do {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CUSTOMREQUEST  => strtoupper($method),
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => $this->timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }

            $raw    = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch);

            $attempt++;
            $retry = ($raw === false || $status >= 500) && $attempt <= $this->maxRetries;

            if ($retry && $attempt > 1) {
                usleep(200_000 * $attempt); // 400ms, 600ms, …
            }
        } while ($retry);

        if ($raw === false) {
            throw new ScorimmoApiException('cURL request failed', 0);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true) ?? [];

        if ($status < 200 || $status >= 300) {
            if (!$authenticate && $status === 401) {
                throw new ScorimmoAuthException($data['message'] ?? 'Authentication failed');
            }
            throw new ScorimmoApiException(
                $data['message'] ?? 'API error',
                $status,
                $data['code'] ?? null,
            );
        }

        return $data;
    }
}
