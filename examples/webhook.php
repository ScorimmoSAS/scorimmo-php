<?php

/**
 * Example: Receive Scorimmo webhooks in a plain PHP endpoint (API v2).
 *
 * Place this file at your webhook URL (e.g. https://your-crm.com/webhook/scorimmo.php).
 *
 * Headers envoyés par Scorimmo sur chaque requête webhook v2 :
 *  - X-Signature-256    : sha256=<hex(hmac_sha256(rawBody, secret))> — vérifie l'origine
 *  - X-Scorimmo-Event   : nom sémantique (ex: 'lead.created') ; 'webhook.<name>' pour un événement futur
 *  - X-Scorimmo-Version : date de la version d'API (ex: '2026-04-20')
 *  - User-Agent         : 'Scorimmo/<app_version>'
 *
 * IMPORTANT : le corps brut (php://input) doit être lu AVANT tout json_decode(),
 * sinon la signature ne pourra pas être vérifiée.
 *
 * Idempotence : Scorimmo effectue jusqu'à 6 livraisons (retries exponentiels).
 * Dédupliquez par (event, lead_id, created_at/updated_at) côté récepteur.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    signatureSecret: $_ENV['SCORIMMO_WEBHOOK_SIGNATURE_SECRET'] ?? 'change-me',
    // signatureHeader: 'X-Signature-256', // valeur par défaut
);

$headers = getallheaders() ?: [];
$rawBody = file_get_contents('php://input');

try {
    $webhook->handle($headers, $rawBody, [

        /**
         * Nouveau lead reçu.
         * Payload : id, store_id, interest, status, origin, contact_type, seller_present_on_creation,
         * customer (first_name, last_name, email, phone, other_phone, pro, legal_name, former, ...),
         * seller (id, first_name, last_name, email, is_virtual?),
         * requests (array de biens avec clés en français : "Type de bien", "Prix", "Surface", "Ville", ...),
         * additional_fields (purpose, residence_type, funding_type, ...),
         * comments (array), external_lead_id?, external_customer_id?.
         */
        'new_lead' => function (array $lead): void {
            $name = trim(($lead['customer']['first_name'] ?? '') . ' ' . ($lead['customer']['last_name'] ?? ''));
            error_log("[new_lead] #{$lead['id']} — {$name} — {$lead['interest']}");
            // TODO: créer le contact dans votre CRM (idempotent : dédupliquer sur lead['id'])
        },

        /**
         * Lead mis à jour — payload sparse : uniquement les champs modifiés
         * (peut inclure customer, seller, requests, additional_fields, store_id, ...).
         */
        'update_lead' => function (array $event): void {
            error_log("[update_lead] #{$event['id']} mis à jour le {$event['updated_at']}");
            // TODO: merger les changements dans votre CRM
        },

        /**
         * Nouveau commentaire/note. Payload : lead_id, comment, created_at, external_lead_id?.
         */
        'new_comment' => function (array $event): void {
            error_log("[new_comment] Lead #{$event['lead_id']}: \"{$event['comment']}\"");
        },

        /**
         * Rendez-vous. Payload : lead_id, start_time, location, detail (nullable),
         * comment, created_at, external_lead_id?.
         */
        'new_rdv' => function (array $event): void {
            error_log("[new_rdv] Lead #{$event['lead_id']}: {$event['start_time']} — " . ($event['detail'] ?? 'RDV'));
        },

        /**
         * Rappel. Payload : lead_id, start_time, detail ('offer'|'recontact'),
         * comment, created_at, external_lead_id?.
         */
        'new_reminder' => function (array $event): void {
            error_log("[new_reminder] Lead #{$event['lead_id']}: {$event['start_time']} — {$event['detail']}");
        },

        /**
         * Lead clôturé. Payload : lead_id, status (libellé français :
         * 'Succès', 'Fermé', 'Fermé par l\'opérateur'), close_reason?, external_lead_id?.
         */
        'closure_lead' => function (array $event): void {
            $reason = $event['close_reason'] ?? '—';
            error_log("[closure_lead] Lead #{$event['lead_id']} — {$event['status']}: {$reason}");
        },

        /**
         * Événement futur non encore modélisé (arrivé avec X-Scorimmo-Event: webhook.<name>).
         * Loguez pour analyse : Scorimmo peut ajouter de nouveaux événements sans breaking change.
         */
        'unknown' => function (array $event): void {
            error_log('[scorimmo.unknown_event] ' . json_encode($event));
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
