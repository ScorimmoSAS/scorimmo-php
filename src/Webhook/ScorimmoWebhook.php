<?php

namespace Scorimmo\Webhook;

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

/**
 * Réception et validation des webhooks Scorimmo (API v2).
 *
 * En V2, l'authentification des webhooks est **optionnelle** — la sécurisation de l'endpoint
 * est à la charge de l'intégrateur. Trois mécanismes sont couramment utilisés (isolés ou combinés) :
 *
 *  1. Signature HMAC-SHA256 (fortement recommandé) — Scorimmo signe le corps brut de la
 *     requête avec un secret partagé et envoie la signature dans un header dédié
 *     (par défaut : `X-Signature-256`, valeur `sha256=<hex>`). Le SDK la vérifie en temps
 *     constant via {@see hash_equals()}.
 *
 *  2. HTTP Basic auth via URL — l'endpoint est enregistré comme `https://user:pass@host/path`.
 *     L'authentification est déléguée au serveur/framework, transparente pour le SDK.
 *
 *  3. Restriction réseau (IP whitelist, VPN, mTLS…) — hors périmètre du SDK.
 *
 * Ce SDK gère uniquement le mécanisme 1. Pour l'activer, passez `signatureSecret` au
 * constructeur ; sinon, aucune vérification n'est effectuée et le payload est simplement parsé.
 *
 * Headers envoyés par Scorimmo sur chaque requête webhook :
 *  - {signatureHeader}  : signature HMAC — présent uniquement si vous avez configuré un secret
 *  - X-Scorimmo-Event   : nom sémantique de l'événement (ex: 'lead.created', 'lead.updated')
 *  - X-Scorimmo-Version : date de version de l'API émettrice (ex: '2026-04-20')
 *  - User-Agent         : 'Scorimmo/<app_version>' (version applicative)
 *
 * Événements émis (valeurs du champ `event` du payload, mapping vers X-Scorimmo-Event) :
 *  new_lead       → lead.created
 *  update_lead    → lead.updated
 *  closure_lead   → lead.closed
 *  new_comment    → lead.comment_added
 *  new_rdv        → lead.appointment_created
 *  new_reminder   → lead.reminder_created
 *
 * Tout événement futur inconnu est diffusé sous la forme `webhook.<internal_event>` dans
 * X-Scorimmo-Event ; le champ `event` du payload garde son nom interne. Les événements
 * inconnus sont routés vers le handler spécial 'unknown' s'il est enregistré, sinon ignorés.
 *
 * Idempotence : Scorimmo garantit une livraison at-least-once (jusqu'à 6 tentatives avec
 * backoff exponentiel jusqu'à 60s). Le corps et la signature sont identiques à chaque retry.
 * Le receveur doit donc être idempotent (dédupliquer par (event, lead_id, created_at)).
 *
 * Utilisation avec signature HMAC (recommandé) :
 *   $webhook = new ScorimmoWebhook(signatureSecret: $secret);
 *   $webhook->handle(getallheaders(), file_get_contents('php://input'), [
 *       'new_lead'    => fn(array $e) => ...,
 *       'update_lead' => fn(array $e) => ...,
 *   ]);
 *
 * Utilisation sans vérification (auth déportée sur le serveur/framework) :
 *   $webhook = new ScorimmoWebhook();
 *
 * ATTENTION : si vous activez la signature, le corps brut doit être lu AVANT tout
 * {@see json_decode()} pour que la signature corresponde. Les frameworks qui parsent
 * automatiquement le JSON doivent exposer le raw body (getContent() en Symfony,
 * $request->getContent() en Laravel, php://input en PHP natif).
 */
class ScorimmoWebhook
{
    /** Préfixe conventionnel de la valeur de signature envoyée par Scorimmo. */
    public const SIGNATURE_PREFIX = 'sha256=';

    /** Nom de header par défaut portant la signature HMAC. */
    public const DEFAULT_SIGNATURE_HEADER = 'X-Signature-256';

    private readonly ?string $signatureSecret;
    private readonly string  $signatureHeader; // lower-case pour comparaison insensible à la casse

    public function __construct(
        ?string $signatureSecret = null,
        string  $signatureHeader = self::DEFAULT_SIGNATURE_HEADER,
    ) {
        // Un secret vide équivaut à ne pas vérifier — normalise en null pour éviter un
        // hash_equals silencieusement toujours faux si l'env var n'est pas renseignée.
        $this->signatureSecret = ($signatureSecret !== null && $signatureSecret !== '') ? $signatureSecret : null;
        $this->signatureHeader = strtolower($signatureHeader);
    }

    /**
     * Valide et parse une requête webhook entrante.
     *
     * @param array<string, string|string[]> $headers  Headers HTTP (insensible à la casse)
     * @param string                         $rawBody  Corps JSON BRUT (avant tout json_decode())
     * @return array<string, mixed>                    Payload de l'événement parsé
     *
     * @throws WebhookAuthException       Signature manquante ou invalide (uniquement si un secret a été configuré)
     * @throws WebhookValidationException Payload non valide (JSON malformé ou champ 'event' manquant)
     */
    public function parse(array $headers, string $rawBody): array
    {
        if ($this->signatureSecret !== null) {
            $this->assertSignature($headers, $rawBody);
        }

        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            throw new WebhookValidationException('Payload must be a valid JSON object');
        }

        if (empty($payload['event']) || !is_string($payload['event'])) {
            throw new WebhookValidationException('Missing or invalid "event" field in payload');
        }

        return $payload;
    }

    /**
     * Dispatche un événement parsé vers le handler correspondant.
     *
     * @param array<string, mixed>    $event    Payload parsé (retourné par parse())
     * @param array<string, callable> $handlers Map nom-événement => callable
     *
     * Clés supportées : new_lead, update_lead, new_comment, new_rdv, new_reminder, closure_lead.
     * La clé spéciale 'unknown' capture tous les événements non reconnus (utile pour recevoir
     * les événements futurs émis en `webhook.<name>`).
     */
    public function dispatch(array $event, array $handlers): void
    {
        $eventName = $event['event'] ?? 'unknown';
        $handler   = $handlers[$eventName] ?? $handlers['unknown'] ?? null;

        if ($handler !== null) {
            $handler($event);
        }
    }

    /**
     * Parse et dispatche un webhook en une seule opération (méthode de commodité).
     *
     * @param array<string, string|string[]> $headers
     * @param string                         $rawBody
     * @param array<string, callable>        $handlers
     *
     * @throws WebhookAuthException
     * @throws WebhookValidationException
     */
    public function handle(array $headers, string $rawBody, array $handlers): void
    {
        $event = $this->parse($headers, $rawBody);
        $this->dispatch($event, $handlers);
    }

    /**
     * Vérifie une signature HMAC-SHA256 en temps constant.
     *
     * Utilisable indépendamment de parse() si vous souhaitez traiter la vérification vous-même
     * (par exemple pour logger avant validation).
     *
     * @param string $rawBody      Corps brut de la requête
     * @param string $headerValue  Valeur du header de signature (avec ou sans préfixe 'sha256=')
     * @param string $secret       Secret partagé configuré côté Scorimmo
     */
    public function verifySignature(string $rawBody, string $headerValue, string $secret): bool
    {
        $received = str_starts_with($headerValue, self::SIGNATURE_PREFIX)
            ? substr($headerValue, strlen(self::SIGNATURE_PREFIX))
            : $headerValue;

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $received);
    }

    /**
     * Indique si le webhook vérifie la signature HMAC des requêtes entrantes.
     * false = aucun secret configuré, aucune vérification effectuée par le SDK.
     */
    public function verifiesSignature(): bool
    {
        return $this->signatureSecret !== null;
    }

    /**
     * Extrait le nom sémantique de l'événement depuis le header X-Scorimmo-Event.
     * Utile pour logger ou router avant même de parser le payload.
     *
     * @param  array<string, string|string[]> $headers
     * @return string|null  Ex: 'lead.created', 'lead.updated', 'webhook.<name>' pour un
     *                      événement futur inconnu, ou null si le header est absent.
     */
    public function getSemanticEvent(array $headers): ?string
    {
        return $this->headerValue($headers, 'x-scorimmo-event');
    }

    /**
     * Extrait la version de l'API Scorimmo depuis le header X-Scorimmo-Version.
     * Il s'agit d'une date (format `YYYY-MM-DD`), pas d'un numéro sémantique.
     *
     * @param  array<string, string|string[]> $headers
     * @return string|null  Ex: '2026-04-20', ou null si le header est absent.
     */
    public function getApiVersion(array $headers): ?string
    {
        return $this->headerValue($headers, 'x-scorimmo-version');
    }

    /**
     * @param array<string, string|string[]> $headers
     * @throws WebhookAuthException
     */
    private function assertSignature(array $headers, string $rawBody): void
    {
        $received = $this->headerValue($headers, $this->signatureHeader);
        if ($received === null) {
            throw new WebhookAuthException(
                sprintf('Missing webhook signature header "%s"', $this->signatureHeader)
            );
        }
        if (!$this->verifySignature($rawBody, $received, (string) $this->signatureSecret)) {
            throw new WebhookAuthException('Invalid webhook signature');
        }
    }

    /**
     * Lit une valeur de header insensible à la casse, en gérant à la fois les valeurs scalaires
     * ({@see getallheaders()}) et les listes ({@see \Symfony\Component\HttpFoundation\HeaderBag::all()}).
     *
     * @param array<string, string|string[]> $headers
     */
    private function headerValue(array $headers, string $lowerKey): ?string
    {
        $normalized = array_change_key_case($headers, CASE_LOWER);
        $value      = $normalized[$lowerKey] ?? null;

        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value !== null ? (string) $value : null;
    }
}
