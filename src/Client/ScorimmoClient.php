<?php

namespace Scorimmo\Client;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;

/**
 * Client HTTP principal de l'API Scorimmo v2.
 *
 * Gère l'authentification JWT (access token + refresh token), le renouvellement
 * automatique des tokens expirés et expose toutes les ressources de l'API.
 *
 * Authentification par identifiants :
 *   $client = new ScorimmoClient(email: 'user@agence.fr', password: 'secret');
 *
 * Authentification par refresh token (sans exposer les identifiants) :
 *   $client = new ScorimmoClient(refreshToken: $tokenPersisté);
 *   // récupérer le nouveau refresh token après le premier appel :
 *   $client->getRefreshToken();
 *
 * Avec logger PSR-3 (Monolog, etc.) :
 *   $client = new ScorimmoClient(email: '...', password: '...', logger: $logger);
 *
 * Avec client HTTP personnalisé (tests, proxy, etc.) :
 *   $client = new ScorimmoClient(email: '...', password: '...', http: $guzzleClient);
 *
 * Ressources disponibles :
 *  @property-read LeadsResource            $leads            Demandes de contact
 *  @property-read AppointmentsResource     $appointments     Rendez-vous
 *  @property-read CommentsResource         $comments         Commentaires et notes
 *  @property-read RemindersResource        $reminders        Rappels / relances
 *  @property-read RequestsResource         $requests         Biens recherchés ou proposés
 *  @property-read StoresResource           $stores           Points de vente
 *  @property-read UsersResource            $users            Conseillers et managers
 *  @property-read CustomersResource        $customers        Contacts / prospects
 *  @property-read StatusResource           $status           Référentiel des statuts (labels + sous-statuts)
 *  @property-read OriginsResource          $origins          Référentiel des origines
 *  @property-read AdditionalFieldsResource $additionalFields Champs additionnels par agence/intérêt
 *  @property-read RequestFieldsResource    $requestFields    Champs de demande par agence/intérêt
 *  @property-read FormResource             $form             Soumission de formulaires publics (ROLE_API_FORM_WRITE)
 *  @property-read WebCallbacksResource     $webCallbacks     Déclenchement d'appels sortants (auth par clé WCB)
 */
class ScorimmoClient
{
    // ── Token state ────────────────────────────────────────────────────────────────

    private ?string           $accessToken    = null;
    private ?string           $refreshToken   = null;
    private ?DateTimeImmutable $tokenExpiresAt = null;

    // ── HTTP & logging ─────────────────────────────────────────────────────────────

    private readonly ClientInterface $http;
    private readonly LoggerInterface $logger;

    // ── Ressources exposées ────────────────────────────────────────────────────────

    public readonly LeadsResource            $leads;
    public readonly AppointmentsResource     $appointments;
    public readonly CommentsResource         $comments;
    public readonly RemindersResource        $reminders;
    public readonly RequestsResource         $requests;
    public readonly StoresResource           $stores;
    public readonly UsersResource            $users;
    public readonly CustomersResource        $customers;
    public readonly StatusResource           $status;
    public readonly OriginsResource          $origins;
    public readonly AdditionalFieldsResource $additionalFields;
    public readonly RequestFieldsResource    $requestFields;
    public readonly FormResource             $form;
    public readonly WebCallbacksResource     $webCallbacks;

    public function __construct(
        private readonly ?string $email = null,
        private readonly ?string $password = null,
        private readonly string $baseUrl = 'https://pro.scorimmo.com',
        ?LoggerInterface $logger = null,
        ?ClientInterface $http = null,
        string|null $refreshToken = null,
    ) {
        if ($email === null && $refreshToken === null) {
            throw new \InvalidArgumentException(
                'ScorimmoClient requires either email+password or a refreshToken.'
            );
        }

        $this->logger = $logger ?? new NullLogger();
        $this->http   = $http ?? new Client([
            'base_uri'        => rtrim($this->baseUrl, '/'),
            'timeout'         => 25.0,
            'connect_timeout' => 10.0,
            'http_errors'     => false,
            'headers'         => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);

        if ($refreshToken !== null) {
            $this->refreshToken = $refreshToken;
        }

        $this->leads            = new LeadsResource($this);
        $this->appointments     = new AppointmentsResource($this);
        $this->comments         = new CommentsResource($this);
        $this->reminders        = new RemindersResource($this);
        $this->requests         = new RequestsResource($this);
        $this->stores           = new StoresResource($this);
        $this->users            = new UsersResource($this);
        $this->customers        = new CustomersResource($this);
        $this->status           = new StatusResource($this);
        $this->origins          = new OriginsResource($this);
        $this->additionalFields = new AdditionalFieldsResource($this);
        $this->requestFields    = new RequestFieldsResource($this);
        $this->form             = new FormResource($this);
        $this->webCallbacks     = new WebCallbacksResource($this);
    }

    // ── Gestion des tokens ────────────────────────────────────────────────────────

    /**
     * Retourne un access token valide.
     *
     * Ordre de priorité :
     *  1. Access token encore valide → retourné directement
     *  2. Refresh token disponible  → échangé contre un nouvel access token
     *  3. Email + password           → authentification complète
     *
     * Si le refresh token est rejeté et que des identifiants sont disponibles,
     * le client bascule automatiquement sur l'authentification email/password.
     *
     * @throws ScorimmoAuthException  Si toutes les tentatives d'authentification échouent
     */
    public function getToken(): string
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt !== null && $this->tokenExpiresAt > new DateTimeImmutable()) {
            return $this->accessToken;
        }

        if ($this->refreshToken !== null) {
            try {
                $this->exchangeRefreshToken($this->refreshToken);
                return $this->accessToken ?? throw new \LogicException('Token exchange succeeded but access_token is not set');
            } catch (ScorimmoAuthException $e) {
                if ($this->email === null) {
                    throw $e;
                }
                $this->logger->warning('[Scorimmo] Refresh token rejected, falling back to email/password auth');
                $this->refreshToken = null;
            }
        }

        if ($this->email === null || $this->password === null) {
            throw new ScorimmoAuthException(
                'Cannot authenticate: no valid refresh token and no email/password credentials provided.'
            );
        }

        $this->logger->info('[Scorimmo] Obtaining new access token for {email}', [
            'email' => $this->email,
        ]);

        $response = $this->rawRequest('POST', '/api/v2/auth/token', [
            'email'    => $this->email,
            'password' => $this->password,
        ], authenticate: false);

        if (!isset($response['access_token'])) {
            throw new ScorimmoAuthException('Authentication failed: no access_token in response');
        }

        $this->applyTokenResponse($response);

        $this->logger->info('[Scorimmo] Access token obtained, expires at {expires_at}', [
            'expires_at' => $this->tokenExpiresAt?->format(DATE_ATOM),
        ]);

        return $this->accessToken ?? throw new \LogicException('Authentication succeeded but access_token is not set');
    }

    /**
     * Échange un refresh token contre un nouvel access token (POST /api/v2/auth/refresh).
     * Met à jour l'état interne : les requêtes suivantes utilisent le nouveau token.
     *
     * Chaque refresh token ne peut être utilisé qu'une seule fois (rotation automatique).
     *
     * @param  string $refreshToken  Token de renouvellement obtenu lors du dernier login
     * @return array<string, mixed>  Nouvelle paire de tokens (access_token, refresh_token, expires_at…)
     * @throws ScorimmoAuthException Si le refresh token est invalide ou révoqué
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->exchangeRefreshToken($refreshToken);
    }

    /**
     * Échange le refresh token contre de nouveaux tokens et met à jour l'état interne.
     *
     * @return array<string, mixed>
     * @throws ScorimmoAuthException
     */
    private function exchangeRefreshToken(string $refreshToken): array
    {
        $this->logger->info('[Scorimmo] Refreshing access token');

        $response = $this->rawRequest('POST', '/api/v2/auth/refresh', [
            'refresh_token' => $refreshToken,
        ], authenticate: false);

        if (!isset($response['access_token'])) {
            throw new ScorimmoAuthException('Token refresh failed: no access_token in response');
        }

        $this->applyTokenResponse($response);

        $this->logger->info('[Scorimmo] Token refreshed, expires at {expires_at}', [
            'expires_at' => $this->tokenExpiresAt?->format(DATE_ATOM),
        ]);

        return $response;
    }

    /**
     * Retourne le refresh token courant (disponible après le premier appel authentifié).
     * Utile pour persister la session et passer à refreshAccessToken() au prochain démarrage.
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Révoque un refresh token spécifique, ou tous les refresh tokens du compte si null.
     *
     * @param  string|null $refreshToken  null = révoquer tous les tokens
     * @return array<string, mixed>
     * @throws ScorimmoApiException
     */
    public function revokeToken(?string $refreshToken = null): array
    {
        $this->logger->info('[Scorimmo] Revoking token(s)', [
            'revoke_all' => $refreshToken === null,
        ]);

        $body = $refreshToken !== null
            ? ['refresh_token' => $refreshToken]
            : ['revoke_all'    => true];

        return $this->rawRequest('POST', '/api/v2/auth/revoke', $body, authenticate: false);
    }

    /**
     * Valide l'access token courant et retourne ses métadonnées.
     * GET /api/v2/auth/validate
     *
     * @return array{
     *   version: string,
     *   status: string,
     *   authenticated: bool,
     *   scopes: string[],
     *   stores: int[],
     *   interests: string[],
     * }
     */
    public function validateToken(): array
    {
        return $this->request('GET', '/api/v2/auth/validate');
    }

    // ── Requêtes HTTP ──────────────────────────────────────────────────────────────

    /**
     * Effectue une requête authentifiée vers l'API.
     *
     * @param  mixed $body  Corps JSON ; null = pas de corps
     * @return array<string, mixed>
     * @throws ScorimmoApiException  Erreur HTTP renvoyée par l'API
     * @throws ScorimmoAuthException Échec d'obtention/refresh de l'access token
     */
    public function request(string $method, string $path, mixed $body = null): array
    {
        return $this->rawRequest($method, $path, $body, authenticate: true);
    }

    /**
     * Effectue une requête sans authentification Bearer (utilisé par les endpoints qui portent
     * leur propre mécanisme d'auth dans le body, ex: POST /api/v2/webcallbacks avec sa clé WCB).
     *
     * @param  mixed $body
     * @return array<string, mixed>
     * @throws ScorimmoApiException  Erreur HTTP renvoyée par l'API
     * @throws ScorimmoAuthException Réponse 401 renvoyée par l'endpoint
     */
    public function requestUnauthenticated(string $method, string $path, mixed $body = null): array
    {
        return $this->rawRequest($method, $path, $body, authenticate: false);
    }

    /**
     * @return array<string, mixed>
     * @throws ScorimmoApiException
     * @throws ScorimmoAuthException
     */
    private function rawRequest(string $method, string $path, mixed $body = null, bool $authenticate = true): array
    {
        $method  = strtoupper($method);
        $options = [];

        if ($authenticate) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->getToken();
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        $this->logger->debug('[Scorimmo] → {method} {path}', [
            'method' => $method,
            'path'   => $path,
        ]);

        $t0 = microtime(true);

        try {
            $response = $this->http->request($method, $path, $options);
        } catch (ConnectException $e) {
            $this->logger->error('[Scorimmo] Connection error on {method} {path}: {message}', [
                'method'  => $method,
                'path'    => $path,
                'message' => $e->getMessage(),
            ]);
            throw new ScorimmoApiException('Connection failed: ' . $e->getMessage(), 0);
        } catch (TransferException $e) {
            $this->logger->error('[Scorimmo] Transfer error on {method} {path}: {message}', [
                'method'  => $method,
                'path'    => $path,
                'message' => $e->getMessage(),
            ]);
            throw new ScorimmoApiException('HTTP error: ' . $e->getMessage(), 0);
        }

        $status = $response->getStatusCode();
        $ms     = round((microtime(true) - $t0) * 1000);

        $this->logger->debug('[Scorimmo] ← {status} {method} {path} ({ms}ms)', [
            'status' => $status,
            'method' => $method,
            'path'   => $path,
            'ms'     => $ms,
        ]);

        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getBody(), true) ?? [];

        if ($status < 200 || $status >= 300) {
            $message = $data['message'] ?? "HTTP {$status}";

            if (!$authenticate && $status === 401) {
                $this->logger->error('[Scorimmo] Authentication failed on {path}: {message}', [
                    'path'    => $path,
                    'message' => $message,
                ]);
                throw new ScorimmoAuthException($message);
            }

            $this->logger->error('[Scorimmo] API {status} on {method} {path}: {message}', [
                'status'  => $status,
                'method'  => $method,
                'path'    => $path,
                'message' => $message,
                'code'    => $data['code'] ?? null,
            ]);

            throw new ScorimmoApiException($message, $status, $data['code'] ?? null);
        }

        return $data;
    }

    /**
     * Applique la réponse d'authentification et calcule l'expiry avec 60s de marge.
     *
     * @param array<string, mixed> $response
     */
    private function applyTokenResponse(array $response): void
    {
        $this->accessToken    = (string) $response['access_token'];
        $this->refreshToken   = isset($response['refresh_token']) ? (string) $response['refresh_token'] : null;
        $this->tokenExpiresAt = (new DateTimeImmutable((string) $response['expires_at']))->modify('-60 seconds') ?: null;
    }
}
