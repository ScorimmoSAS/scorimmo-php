<?php

namespace Scorimmo\OAuth;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;

/**
 * Flux OAuth 2.1 `authorization_code` + PKCE de l'API Scorimmo v2 (RFC 6749 / RFC 7636).
 *
 * Destiné aux outils tiers qui agissent pour le compte d'un utilisateur sans manipuler son mot
 * de passe. Le client OAuth est public : aucun `client_secret`, la sécurité de l'échange repose
 * sur PKCE. Ces endpoints ne demandent pas d'authentification, d'où une classe distincte de
 * {@see \Scorimmo\Client\ScorimmoClient}, qui exige des identifiants à la construction.
 *
 * Endpoints couverts :
 *  POST  /api/v2/oauth/register    → register()
 *  GET   /api/v2/oauth/authorize   → authorizationUrl() (à ouvrir dans le navigateur de l'utilisateur)
 *  POST  /api/v2/oauth/token       → exchangeCode()
 *  POST  /api/v2/oauth/revoke      → revoke()
 *
 * Une fois les tokens obtenus, le client API prend le relais et renouvelle lui-même l'access token :
 *   $client = new ScorimmoClient(refreshToken: $tokens['refresh_token'], oauthClientId: $clientId);
 */
class ScorimmoOAuth
{
    private readonly ClientInterface $http;

    public function __construct(
        private readonly string $baseUrl = 'https://pro.scorimmo.com',
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri'        => rtrim($this->baseUrl, '/'),
            'timeout'         => 25.0,
            'connect_timeout' => 10.0,
            'http_errors'     => false,
            'headers'         => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'User-Agent'   => 'scorimmo-php/' . ScorimmoClient::VERSION,
            ],
        ]);
    }

    /**
     * Génère un couple PKCE. Conserver `code_verifier` côté serveur jusqu'à exchangeCode(),
     * ne transmettre que `code_challenge` à authorizationUrl().
     *
     * @return array{code_verifier: string, code_challenge: string}
     */
    public static function generatePkce(): array
    {
        $verifier = self::base64Url(random_bytes(48));

        return [
            'code_verifier'  => $verifier,
            'code_challenge' => self::base64Url(hash('sha256', $verifier, true)),
        ];
    }

    /**
     * Génère une valeur `state` à conserver en session, puis à comparer via {@see hash_equals()}
     * à celle reçue sur le `redirect_uri`, pour se prémunir du CSRF.
     */
    public static function generateState(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Enregistre l'outil comme client OAuth. À faire une seule fois par outil, pas par utilisateur.
     *
     * Hors localhost, seuls les hôtes autorisés par Scorimmo sont acceptés, en https. Les adresses
     * refusées sont retirées sans erreur : comparer `redirect_uris` de la réponse à la demande.
     *
     * @param  string[] $redirectUris  URL de callback de l'outil
     * @param  string|null $clientName Nom affiché sur l'écran de consentement (60 caractères max)
     * @return array{client_id: string, client_id_issued_at: int, redirect_uris: string[], grant_types: string[], response_types: string[]}
     * @throws \InvalidArgumentException Si $redirectUris est vide
     * @throws ScorimmoApiException      400 si aucune adresse n'est acceptable (`invalid_redirect_uri`)
     */
    public function register(array $redirectUris, ?string $clientName = null): array
    {
        if ($redirectUris === []) {
            throw new \InvalidArgumentException('register() requires at least one redirect URI');
        }

        return $this->post('/api/v2/oauth/register', [
            'redirect_uris' => array_values($redirectUris),
        ] + ($clientName !== null ? ['client_name' => $clientName] : []));
    }

    /**
     * Construit l'URL de consentement vers laquelle rediriger le navigateur de l'utilisateur.
     *
     * Après validation, Scorimmo redirige vers `$redirectUri` avec `?code=...&state=...`,
     * ou `?error=...&error_description=...&state=...` en cas de refus.
     *
     * @param string $redirectUri Strictement identique à l'une des URL enregistrées
     */
    public function authorizationUrl(string $clientId, string $redirectUri, string $codeChallenge, string $state): string
    {
        return rtrim($this->baseUrl, '/') . '/api/v2/oauth/authorize?' . http_build_query([
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'state'                 => $state,
            'code_challenge'        => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Échange le code reçu sur le `redirect_uri` contre une paire de tokens. À appeler depuis
     * le backend : le code est à usage unique et expire au bout de 60 secondes.
     *
     * Le refresh token retourné tourne à chaque renouvellement. Présenter à nouveau un token déjà
     * remplacé révoque toute la chaîne : l'utilisateur devra autoriser l'outil à nouveau.
     *
     * @param  string $redirectUri Strictement identique à celui passé à authorizationUrl()
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}
     * @throws ScorimmoApiException 400 (`invalid_grant`, `invalid_client`) ou 403 (`access_denied`), exposés dans `$apiCode`
     */
    public function exchangeCode(string $code, string $codeVerifier, string $redirectUri, string $clientId): array
    {
        return $this->post('/api/v2/oauth/token', [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'code_verifier' => $codeVerifier,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $clientId,
        ]);
    }

    /**
     * Révoque un refresh token OAuth (RFC 7009). L'API répond 200 même si le token est inconnu ;
     * l'access token déjà émis reste valable jusqu'à son expiration.
     *
     * @throws ScorimmoApiException
     */
    public function revoke(string $refreshToken): void
    {
        $this->post('/api/v2/oauth/revoke', ['token' => $refreshToken]);
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     * @throws ScorimmoApiException
     */
    private function post(string $path, array $body): array
    {
        try {
            $response = $this->http->request('POST', $path, ['json' => $body]);
        } catch (TransferException $e) {
            throw new ScorimmoApiException('HTTP error: ' . $e->getMessage(), 0);
        }

        $status = $response->getStatusCode();

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($status < 200 || $status >= 300) {
            throw new ScorimmoApiException(
                $data['error_description'] ?? $data['error'] ?? "HTTP {$status}",
                $status,
                $data['error'] ?? null,
                $data,
            );
        }

        return $data;
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
