<?php

namespace Scorimmo\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;
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

    // ── request() — retry unique sur 401 ──────────────────────────────────────────

    public function testAuthenticatedRequestRetriesOnceAfter401(): void
    {
        $http = $this->mockHttp();
        // 1) auth initiale (POST /auth/token) → tok-1
        // 2) GET protégé avec tok-1 → 401
        // 3) auth (POST /auth/token) → tok-2
        // 4) GET protégé avec tok-2 → 200
        $http->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->httpResponse($this->tokenResponse('tok-1', 'ref-1')),
                $this->httpResponse(['message' => 'Token expired'], 401),
                $this->httpResponse($this->tokenResponse('tok-2', 'ref-2')),
                $this->httpResponse(['id' => 42, 'ok' => true]),
            );

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $result = $client->request('GET', '/api/v2/leads/42');

        $this->assertSame(42, $result['id']);
        $this->assertSame('tok-2', $client->getToken());
    }

    public function testAuthenticatedRequestDoesNotRetryTwiceOn401(): void
    {
        $http = $this->mockHttp();
        // Deux 401 successifs sur l'endpoint protégé → l'exception doit remonter.
        $http->expects($this->exactly(4))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->httpResponse($this->tokenResponse('tok-1')),
                $this->httpResponse(['message' => 'Unauthorized'], 401),
                $this->httpResponse($this->tokenResponse('tok-2')),
                $this->httpResponse(['message' => 'Unauthorized'], 401),
            );

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);

        $this->expectException(ScorimmoApiException::class);
        $client->request('GET', '/api/v2/leads/42');
    }

    // ── applyTokenResponse — parsing d'expires_at ─────────────────────────────────

    public function testAuthAcceptsExpiresAtAsUnixTimestamp(): void
    {
        $futureUnix = time() + 3600;

        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse([
                'access_token'  => 'tok-unix',
                'refresh_token' => 'ref-unix',
                'expires_at'    => $futureUnix, // entier Unix
            ]));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $this->assertSame('tok-unix', $client->getToken());
        // Un second appel doit renvoyer le token en cache (pas d'appel HTTP supplémentaire attendu par le mock).
        $this->assertSame('tok-unix', $client->getToken());
    }

    public function testAuthAcceptsExpiresAtAsNumericString(): void
    {
        $futureUnix = (string) (time() + 3600);

        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse([
                'access_token'  => 'tok-numstr',
                'refresh_token' => 'ref-numstr',
                'expires_at'    => $futureUnix,
            ]));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $this->assertSame('tok-numstr', $client->getToken());
        $this->assertSame('tok-numstr', $client->getToken());
    }

    public function testAuthFallsBackToExpiresInWhenExpiresAtIsMissing(): void
    {
        $http = $this->mockHttp();
        $http->expects($this->once())
            ->method('request')
            ->willReturn($this->httpResponse([
                'access_token'  => 'tok-expin',
                'refresh_token' => 'ref-expin',
                'expires_in'    => 3600, // pas d'expires_at
            ]));

        $client = new ScorimmoClient(email: 'user@test.com', password: 'secret', http: $http);
        $this->assertSame('tok-expin', $client->getToken());
        // Le cache doit être valide → pas de second appel HTTP.
        $this->assertSame('tok-expin', $client->getToken());
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
