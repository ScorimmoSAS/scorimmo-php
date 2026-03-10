<?php

/**
 * Example: Receive Scorimmo webhooks in a plain PHP endpoint
 *
 * Place this file at your webhook URL (e.g. https://your-crm.com/webhook/scorimmo.php)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    headerValue: $_ENV['SCORIMMO_WEBHOOK_SECRET'] ?? 'change-me',
    headerKey: 'X-Scorimmo-Key',
);

$headers = getallheaders() ?: [];
$rawBody = file_get_contents('php://input');

try {
    $webhook->handle($headers, $rawBody, [
        'new_lead' => function (array $lead): void {
            $name = trim(($lead['customer']['first_name'] ?? '') . ' ' . ($lead['customer']['last_name'] ?? ''));
            error_log("[new_lead] #{$lead['id']} — {$name}");
            // TODO: create contact in your CRM
        },

        'update_lead' => function (array $event): void {
            error_log("[update_lead] #{$event['id']} updated at {$event['updated_at']}");
            // TODO: sync changes to your CRM
        },

        'new_comment' => function (array $event): void {
            error_log("[new_comment] Lead #{$event['lead_id']}: \"{$event['comment']}\"");
            // TODO: add note/activity in your CRM
        },

        'new_rdv' => function (array $event): void {
            error_log("[new_rdv] Lead #{$event['lead_id']}: {$event['start_time']}");
            // TODO: create appointment in your CRM
        },

        'new_reminder' => function (array $event): void {
            error_log("[new_reminder] Lead #{$event['lead_id']}: {$event['start_time']}");
        },

        'closure_lead' => function (array $event): void {
            error_log("[closure_lead] Lead #{$event['lead_id']} — {$event['status']}");
            // TODO: archive contact in your CRM
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
