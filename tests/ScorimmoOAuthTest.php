<?php

namespace Scorimmo\Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;
use Scorimmo\OAuth\ScorimmoOAuth;

class ScorimmoOAuthTest extends TestCase
{
    private function httpResponse(?array $body, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
    }

    private function oauthTokens(string $access = 'jwt-oauth', string $refresh = 'rotated'): array
    {
        return ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => 3600, 'refresh_token' => $refresh, 'scope' => 'api_v2'];
    }

    private function expectPost(string $path, array $json, Response $response): ClientInterface
    {
        $http = $this->createMock(ClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with('POST', $path, ['json' => $json])
            ->willReturn($response);

        return $http;
    }

    // ── PKCE ──────────────────────────────────────────────────────────────────────

    public function testGeneratePkceProducesS256Challenge(): void
    {
        $pkce = ScorimmoOAuth::generatePkce();

        $this->assertGreaterThanOrEqual(43, strlen($pkce['code_verifier']));
        $this->assertLessThanOrEqual(128, strlen($pkce['code_verifier']));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $pkce['code_verifier']);
        $this->assertSame(
            rtrim(strtr(base64_encode(hash('sha256', $pkce['code_verifier'], true)), '+/', '-_'), '='),
            $pkce['code_challenge'],
        );
    }

    public function testGeneratedValuesAreUnique(): void
    {
        $this->assertNotSame(ScorimmoOAuth::generatePkce(), ScorimmoOAuth::generatePkce());
        $this->assertNotSame(ScorimmoOAuth::generateState(), ScorimmoOAuth::generateState());
    }

    // ── Enregistrement ────────────────────────────────────────────────────────────

    public function testRegisterPostsRedirectUrisAndName(): void
    {
        $http = $this->expectPost(
            '/api/v2/oauth/register',
            ['redirect_uris' => ['https://app.test/cb'], 'client_name' => 'Outil'],
            $this->httpResponse(['client_id' => 'abc'], 201),
        );

        $this->assertSame('abc', (new ScorimmoOAuth(http: $http))->register(['https://app.test/cb'], 'Outil')['client_id']);
    }

    public function testRegisterRejectsEmptyRedirectUris(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ScorimmoOAuth(http: $this->createMock(ClientInterface::class)))->register([]);
    }

    // ── Autorisation ──────────────────────────────────────────────────────────────

    public function testAuthorizationUrlContainsEncodedParameters(): void
    {
        $url = (new ScorimmoOAuth('https://crm.test/', $this->createMock(ClientInterface::class)))
            ->authorizationUrl('abc', 'https://app.test/cb?x=1', 'challenge', 'etat');

        $this->assertStringStartsWith('https://crm.test/api/v2/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame([
            'client_id'             => 'abc',
            'redirect_uri'          => 'https://app.test/cb?x=1',
            'state'                 => 'etat',
            'code_challenge'        => 'challenge',
            'code_challenge_method' => 'S256',
        ], $query);
    }

    // ── Échange et révocation ─────────────────────────────────────────────────────

    public function testExchangeCodePostsAuthorizationCodeGrant(): void
    {
        $http = $this->expectPost('/api/v2/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => 'code-1',
            'code_verifier' => 'verifier',
            'redirect_uri'  => 'https://app.test/cb',
            'client_id'     => 'abc',
        ], $this->httpResponse($this->oauthTokens()));

        $tokens = (new ScorimmoOAuth(http: $http))->exchangeCode('code-1', 'verifier', 'https://app.test/cb', 'abc');

        $this->assertSame('rotated', $tokens['refresh_token']);
    }

    public function testExchangeCodeExposesOAuthError(): void
    {
        $body = ['error' => 'invalid_grant', 'error_description' => 'PKCE verification failed'];
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn($this->httpResponse($body, 400));

        try {
            (new ScorimmoOAuth(http: $http))->exchangeCode('c', 'v', 'https://app.test/cb', 'abc');
            $this->fail('Expected ScorimmoApiException');
        } catch (ScorimmoApiException $e) {
            $this->assertSame('PKCE verification failed', $e->getMessage());
            $this->assertSame('invalid_grant', $e->apiCode);
            $this->assertSame(400, $e->statusCode);
            $this->assertSame($body, $e->body);
        }
    }

    public function testNetworkErrorBecomesApiExceptionWithStatusZero(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willThrowException(new ConnectException('timeout', new Request('POST', '/')));

        try {
            (new ScorimmoOAuth(http: $http))->revoke('token');
            $this->fail('Expected ScorimmoApiException');
        } catch (ScorimmoApiException $e) {
            $this->assertSame(0, $e->statusCode);
        }
    }

    public function testRevokeAcceptsNullBody(): void
    {
        $http = $this->expectPost('/api/v2/oauth/revoke', ['token' => 'refresh'], $this->httpResponse(null));

        (new ScorimmoOAuth(http: $http))->revoke('refresh');
    }

    // ── ScorimmoClient en mode OAuth ──────────────────────────────────────────────

    public function testClientRenewsThroughOAuthEndpoint(): void
    {
        $http = $this->expectPost('/api/v2/oauth/token', [
            'grant_type'    => 'refresh_token',
            'refresh_token' => 'initial',
            'client_id'     => 'abc',
        ], $this->httpResponse($this->oauthTokens()));

        $client = new ScorimmoClient(refreshToken: 'initial', http: $http, oauthClientId: 'abc');

        $this->assertSame('jwt-oauth', $client->getToken());
        $this->assertSame('rotated', $client->getRefreshToken());
    }

    public function testClientWithoutOAuthKeepsAuthRefreshEndpoint(): void
    {
        $http = $this->expectPost('/api/v2/auth/refresh', ['refresh_token' => 'initial'], $this->httpResponse($this->oauthTokens()));

        (new ScorimmoClient(refreshToken: 'initial', http: $http))->getToken();
    }

    public function testClientConvertsInvalidGrantToAuthException(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn($this->httpResponse(['error' => 'invalid_grant', 'error_description' => 'Refresh token already used'], 400));

        $this->expectException(ScorimmoAuthException::class);
        $this->expectExceptionMessage('Refresh token already used');

        (new ScorimmoClient(refreshToken: 'initial', http: $http, oauthClientId: 'abc'))->getToken();
    }

    public function testClientKeepsAccessDeniedAsApiException(): void
    {
        $http = $this->createMock(ClientInterface::class);
        $http->method('request')->willReturn($this->httpResponse(['error' => 'access_denied', 'error_description' => 'Account has no API access'], 403));

        try {
            (new ScorimmoClient(refreshToken: 'initial', http: $http, oauthClientId: 'abc'))->getToken();
            $this->fail('Expected ScorimmoApiException');
        } catch (ScorimmoApiException $e) {
            $this->assertSame('access_denied', $e->apiCode);
            $this->assertSame('Account has no API access', $e->getMessage());
        }
    }

    public function testOAuthClientIdRequiresRefreshToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ScorimmoClient(email: 'user@test.com', password: 'secret', oauthClientId: 'abc');
    }
}
