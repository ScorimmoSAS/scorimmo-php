<?php

namespace Scorimmo\Client;

use DateTimeImmutable;
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;

/**
 * Client HTTP principal de l'API Scorimmo v2.
 *
 * Gère l'authentification JWT (access token + refresh token), le renouvellement
 * automatique des tokens expirés et expose toutes les ressources de l'API.
 *
 * Utilisation rapide :
 *   $client = new ScorimmoClient(email: 'user@agence.fr', password: 'secret');
 *   $leads  = $client->leads->since(new DateTime('-24 hours'));
 *
 * Ressources disponibles :
 *  @property-read LeadsResource        $leads          Demandes de contact
 *  @property-read AppointmentsResource $appointments   Rendez-vous
 *  @property-read CommentsResource     $comments       Commentaires et notes
 *  @property-read RemindersResource    $reminders      Rappels / relances
 *  @property-read RequestsResource     $requests       Biens recherchés ou proposés
 *  @property-read StoresResource       $stores         Points de vente
 *  @property-read UsersResource        $users          Conseillers et managers
 *  @property-read CustomersResource    $customers      Contacts / prospects
 *  @property-read StatusResource       $status         Référentiel des statuts
 */
class ScorimmoClient
{
    // ── Token state (géré en interne, transparent pour l'appelant) ──────────────

    private ?string           $accessToken  = null;
    private ?string           $refreshToken = null;
    private ?DateTimeImmutable $tokenExpiresAt = null;

    // ── Ressources exposées publiquement ─────────────────────────────────────────

    public readonly LeadsResource        $leads;
    public readonly AppointmentsResource $appointments;
    public readonly CommentsResource     $comments;
    public readonly RemindersResource    $reminders;
    public readonly RequestsResource     $requests;
    public readonly StoresResource       $stores;
    public readonly UsersResource        $users;
    public readonly CustomersResource    $customers;
    public readonly StatusResource       $status;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
        private readonly string $baseUrl = 'https://pro.scorimmo.com',
    ) {
        $this->leads        = new LeadsResource($this);
        $this->appointments = new AppointmentsResource($this);
        $this->comments     = new CommentsResource($this);
        $this->reminders    = new RemindersResource($this);
        $this->requests     = new RequestsResource($this);
        $this->stores       = new StoresResource($this);
        $this->users        = new UsersResource($this);
        $this->customers    = new CustomersResource($this);
        $this->status       = new StatusResource($this);
    }

    // ── Gestion des tokens ────────────────────────────────────────────────────────

    /**
     * Retourne un access token valide.
     * Si le token en cache est expiré (ou absent), un nouveau est obtenu automatiquement
     * via le flux email/password (POST /api/v2/auth/token).
     *
     * @throws ScorimmoAuthException  Si l'authentification échoue
     */
    public function getToken(): string
    {
        if ($this->accessToken !== null && $this->tokenExpiresAt > new DateTimeImmutable()) {
            return $this->accessToken;
        }

        $response = $this->rawRequest('POST', '/api/v2/auth/token', [
            'email'    => $this->email,
            'password' => $this->password,
        ], authenticate: false);

        if (!isset($response['access_token'])) {
            throw new ScorimmoAuthException('Authentication failed: no access_token in response');
        }

        $this->applyTokenResponse($response);

        return $this->accessToken;
    }

    /**
     * Échange un refresh token contre un nouvel access token (POST /api/v2/auth/refresh).
     * Met à jour l'état interne du client : les requêtes suivantes utilisent le nouveau token.
     *
     * À utiliser pour prolonger une session sans redemander les credentials.
     * Note : chaque refresh token ne peut être utilisé qu'une seule fois (rotation automatique).
     *
     * @param  string $refreshToken  Token de renouvellement obtenu lors du dernier login
     * @return array<string, mixed>  Nouvelle paire de tokens (access_token, refresh_token, expires_at…)
     * @throws ScorimmoAuthException Si le refresh token est invalide ou révoqué
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->rawRequest('POST', '/api/v2/auth/refresh', [
            'refresh_token' => $refreshToken,
        ], authenticate: false);

        if (isset($response['access_token'])) {
            $this->applyTokenResponse($response);
        }

        return $response;
    }

    /**
     * Retourne le refresh token courant, disponible après le premier appel authentifié.
     * Utile pour persister la session côté appelant et passer à refreshAccessToken().
     */
    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    /**
     * Révoque un refresh token spécifique, ou tous les refresh tokens de l'utilisateur
     * (POST /api/v2/auth/revoke).
     *
     * @param  string|null $refreshToken  Token à révoquer ; null pour révoquer tous les tokens
     * @return array<string, mixed>
     */
    public function revokeToken(?string $refreshToken = null): array
    {
        $body = $refreshToken !== null
            ? ['refresh_token' => $refreshToken]
            : ['revoke_all' => true];

        return $this->rawRequest('POST', '/api/v2/auth/revoke', $body, authenticate: false);
    }

    /**
     * Valide l'access token courant et retourne ses métadonnées (scopes, stores accessibles…).
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

    // ── Requêtes HTTP ─────────────────────────────────────────────────────────────

    /**
     * Effectue une requête authentifiée vers l'API.
     *
     * @param  mixed $body  Corps de la requête (encodé en JSON) ; null = pas de corps
     * @return array<string, mixed>
     * @throws ScorimmoApiException
     */
    public function request(string $method, string $path, mixed $body = null): array
    {
        return $this->rawRequest($method, $path, $body, authenticate: true);
    }

    /**
     * @return array<string, mixed>
     * @throws ScorimmoApiException
     * @throws ScorimmoAuthException
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

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            throw new ScorimmoApiException('cURL request failed: ' . curl_error($ch), 0);
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

    /**
     * Applique la réponse d'authentification (token, refresh token, expiration).
     * Expire 60 secondes avant l'heure officielle pour éviter les cas limites.
     *
     * @param array<string, mixed> $response
     */
    private function applyTokenResponse(array $response): void
    {
        $this->accessToken  = $response['access_token'];
        $this->refreshToken = $response['refresh_token'] ?? null;
        $this->tokenExpiresAt = (new DateTimeImmutable($response['expires_at']))->modify('-60 seconds');
    }
}
