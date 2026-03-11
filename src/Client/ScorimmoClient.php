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

    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $baseUrl = 'https://pro.scorimmo.com',
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
        $expiresAt = $response['token_expirate_at'];
        $this->tokenExpiresAt = (new DateTimeImmutable(is_numeric($expiresAt) ? '@' . $expiresAt : $expiresAt))
            ->modify('-60 seconds');

        return $this->token;
    }

    /**
     * Authenticated JSON request.
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, mixed $body = null): array
    {
        return $this->rawRequest($method, $path, $body, authenticate: true);
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

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

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
