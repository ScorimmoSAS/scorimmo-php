<?php

namespace Scorimmo\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoAuthException;

class ScorimmoClientTest extends TestCase
{
    private function tokenResponse(
        string $accessToken = 'access-abc',
        string $refreshToken = 'refresh-xyz',
        string $expiresIn = '+1 hour',
    ): array {
        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => (new \DateTimeImmutable($expiresIn))->format(\DateTimeInterface::ATOM),
        ];
    }

    private function httpResponse(array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function mockHttp(): MockObject&ClientInterface
    {
        return $this->createMock(ClientInterface::class);
    }

    // ── Construction ──────────────────────────────────────────────────────────────

    public function testConstructorThrowsWithoutAnyCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ScorimmoClient();
    }

    public function testConstructorAcceptsEmailAndPassword(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse($this->tokenResponse()));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $this->assertSame('access-abc', $client->getToken());
    }

    public function testConstructorAcceptsRefreshTokenOnly(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse($this->tokenResponse('access-from-refresh')));

        $client = new ScorimmoClient(refreshToken: 'initial-refresh', http: $http);
        $this->assertSame('access-from-refresh', $client->getToken());
    }

    // ── getToken() — priorité refresh token ───────────────────────────────────────

    public function testGetTokenUsesRefreshTokenBeforeCredentials(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $this->stringContains('/auth/refresh'))
            ->willReturn($this->httpResponse($this->tokenResponse('tok-via-refresh')));

        $client = new ScorimmoClient(
            email: 'user@test.com',
            password: 'secret',
            http: $http,
            refreshToken: 'stored-refresh',
        );

        $this->assertSame('tok-via-refresh', $client->getToken());
    }

    public function testGetTokenReturnsCachedTokenIfStillValid(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->once()) // un seul appel HTTP malgré deux appels getToken()
            ->method('request')
            ->willReturn($this->httpResponse($this->tokenResponse()));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $client->getToken();
        $client->getToken(); // doit retourner le token en cache
    }

    // ── Fallback email/password quand le refresh token est rejeté ─────────────────

    public function testGetTokenFallsBackToCredentialsWhenRefreshFails(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->httpResponse(['message' => 'Invalid refresh token'], 401),
                $this->httpResponse($this->tokenResponse('tok-via-password')),
            );

        $client = new ScorimmoClient(
            email: 'user@test.com',
            password: 'secret',
            http: $http,
            refreshToken: 'expired-refresh',
        );

        $this->assertSame('tok-via-password', $client->getToken());
    }

    public function testGetTokenThrowsWhenOnlyRefreshTokenAndItFails(): void
    {
        $this->expectException(ScorimmoAuthException::class);

        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse(['message' => 'Token revoked'], 401));

        $client = new ScorimmoClient(refreshToken: 'revoked-token', http: $http);
        $client->getToken();
    }

    // ── refreshAccessToken() public ───────────────────────────────────────────────

    public function testRefreshAccessTokenUpdatesInternalState(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->httpResponse($this->tokenResponse('tok-1', 'ref-1')),
                $this->httpResponse($this->tokenResponse('tok-2', 'ref-2')),
            );

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $client->getToken(); // auth initiale → tok-1, ref-1

        $result = $client->refreshAccessToken('ref-1');

        $this->assertSame('tok-2', $result['access_token']);
        $this->assertSame('ref-2', $client->getRefreshToken());
    }

    public function testRefreshAccessTokenThrowsWhenRejected(): void
    {
        $this->expectException(ScorimmoAuthException::class);

        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse(['message' => 'Invalid token'], 401));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $client->refreshAccessToken('bad-token');
    }
}
