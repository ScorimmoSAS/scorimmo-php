<?php

/**
 * Example: Receive Scorimmo webhooks in a plain PHP endpoint
 *
 * Place this file at your webhook URL (e.g. https://your-crm.com/webhook/scorimmo.php)
 *
 * Headers envoyés par Scorimmo sur chaque requête webhook :
 *  - {X-Scorimmo-Key}      : valeur du secret configuré sur le point de vente (authentification)
 *  - X-Scorimmo-Event      : nom sémantique de l'événement (ex: 'lead.created', 'lead.updated')
 *  - X-Scorimmo-Version    : version de l'API émettrice (ex: '2.0.0')
 *  - User-Agent            : 'Scorimmo/<version>'
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    headerValue: $_ENV['SCORIMMO_WEBHOOK_SECRET'] ?? 'change-me',
    headerKey:   'X-Scorimmo-Key',
);

$headers = getallheaders() ?: [];
$rawBody = file_get_contents('php://input');

try {
    $webhook->handle($headers, $rawBody, [

        /**
         * Nouveau lead reçu.
         * Payload inclut : id, store_id, interest, status, origin, purpose, contact_type,
         * customer (first_name, last_name, email, phone…), seller, requests (biens), comments.
         */
        'new_lead' => function (array $lead): void {
            $name = trim(($lead['customer']['first_name'] ?? '') . ' ' . ($lead['customer']['last_name'] ?? ''));
            error_log("[new_lead] #{$lead['id']} — {$name} — {$lead['interest']}");
            // TODO: créer le contact dans votre CRM
        },

        /**
         * Lead mis à jour.
         * Payload inclut : id, updated_at, et uniquement les champs modifiés (merge partiel).
         */
        'update_lead' => function (array $event): void {
            error_log("[update_lead] #{$event['id']} mis à jour le {$event['updated_at']}");
            // TODO: synchroniser les changements dans votre CRM
        },

        /**
         * Nouveau commentaire ou note ajouté sur un lead.
         * Payload inclut : lead_id, comment, created_at, external_lead_id.
         */
        'new_comment' => function (array $event): void {
            error_log("[new_comment] Lead #{$event['lead_id']}: \"{$event['comment']}\"");
            // TODO: ajouter une note/activité dans votre CRM
        },

        /**
         * Rendez-vous planifié sur un lead.
         * Payload inclut : lead_id, start_time, location, detail, comment, external_lead_id.
         */
        'new_rdv' => function (array $event): void {
            error_log("[new_rdv] Lead #{$event['lead_id']}: {$event['start_time']} — {$event['detail']}");
            // TODO: créer l'événement dans votre calendrier CRM
        },

        /**
         * Rappel planifié sur un lead.
         * Payload inclut : lead_id, start_time, detail, comment, external_lead_id.
         */
        'new_reminder' => function (array $event): void {
            error_log("[new_reminder] Lead #{$event['lead_id']}: {$event['start_time']} — {$event['detail']}");
            // TODO: créer le rappel dans votre CRM
        },

        /**
         * Lead clôturé (succès ou échec).
         * Payload inclut : lead_id, status (ex: 'SUCCESS'), close_reason, external_lead_id.
         */
        'closure_lead' => function (array $event): void {
            error_log("[closure_lead] Lead #{$event['lead_id']} — {$event['status']}: {$event['close_reason']}");
            // TODO: archiver le contact dans votre CRM
        },

    ]);

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (WebhookAuthException $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);

} catch (WebhookValidationException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    error_log($e->getMessage());
}
