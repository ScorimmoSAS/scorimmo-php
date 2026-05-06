<?php

namespace Scorimmo\Webhook;

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

/**
 * Réception et validation des webhooks Scorimmo.
 *
 * Scorimmo authentifie ses appels webhook via un header HTTP custom dont la clé et la valeur
 * sont configurées sur chaque point de vente. Ce secret statique doit être communiqué à Scorimmo
 * lors de la mise en place de l'intégration.
 *
 * Headers envoyés par Scorimmo sur chaque requête webhook :
 *  - {headerKey}           : valeur du secret configuré (authentification)
 *  - X-Scorimmo-Event      : nom sémantique de l'événement (ex: 'lead.created', 'lead.updated')
 *  - X-Scorimmo-Version    : version de l'API ayant émis l'événement (ex: '2.0.0')
 *  - User-Agent            : 'Scorimmo/<version>'
 *
 * Correspondance X-Scorimmo-Event ↔ champ 'event' du payload :
 *  new_lead       → lead.created
 *  update_lead    → lead.updated
 *  closure_lead   → lead.closed
 *  new_comment    → lead.comment_added
 *  new_rdv        → lead.appointment_created
 *  new_reminder   → lead.reminder_created
 *
 * Utilisation typique :
 *   $webhook = new ScorimmoWebhook(headerValue: 'my-secret', headerKey: 'X-Api-Key');
 *   $webhook->handle(getallheaders(), file_get_contents('php://input'), [
 *       'new_lead'    => fn(array $e) => ...,
 *       'update_lead' => fn(array $e) => ...,
 *   ]);
 */
class ScorimmoWebhook
{
    private readonly string $headerKey;

    public function __construct(
        private readonly string $headerValue,
        string $headerKey = 'X-Scorimmo-Key',
    ) {
        // Normalise en minuscules pour la comparaison insensible à la casse
        $this->headerKey = strtolower($headerKey);
    }

    /**
     * Valide et parse une requête webhook entrante.
     *
     * @param array<string, string> $headers  Headers HTTP de la requête (insensible à la casse)
     * @param string                $rawBody  Corps JSON brut
     * @return array<string, mixed>           Payload de l'événement parsé
     *
     * @throws WebhookAuthException       Header d'authentification absent ou invalide
     * @throws WebhookValidationException Payload non valide (JSON malformé ou champ 'event' manquant)
     */
    public function parse(array $headers, string $rawBody): array
    {
        $this->assertAuth($headers);

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
     * Clés supportées : new_lead, update_lead, new_comment, new_rdv, new_reminder, closure_lead
     * La clé spéciale 'unknown' capture tous les événements non reconnus.
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
     * @param array<string, string>   $headers
     * @param string                  $rawBody
     * @param array<string, callable> $handlers
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
     * Extrait le nom sémantique de l'événement depuis le header X-Scorimmo-Event.
     * Utile pour logger ou router avant même de parser le payload.
     *
     * @param  array<string, string> $headers
     * @return string|null  Ex: 'lead.created', 'lead.updated', ou null si header absent
     */
    public function getSemanticEvent(array $headers): ?string
    {
        $normalized = array_change_key_case($headers, CASE_LOWER);
        return $normalized['x-scorimmo-event'] ?? null;
    }

    /**
     * Extrait la version de l'API Scorimmo depuis le header X-Scorimmo-Version.
     *
     * @param  array<string, string> $headers
     * @return string|null  Ex: '2.0.0', ou null si header absent
     */
    public function getApiVersion(array $headers): ?string
    {
        $normalized = array_change_key_case($headers, CASE_LOWER);
        return $normalized['x-scorimmo-version'] ?? null;
    }

    /**
     * @param array<string, string> $headers
     * @throws WebhookAuthException
     */
    private function assertAuth(array $headers): void
    {
        // Normalise toutes les clés en minuscules pour la comparaison
        $normalized = array_change_key_case($headers, CASE_LOWER);
        $received   = $normalized[$this->headerKey] ?? null;

        if ($received !== $this->headerValue) {
            throw new WebhookAuthException('Invalid or missing webhook authentication header');
        }
    }
}
