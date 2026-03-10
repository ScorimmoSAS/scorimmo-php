<?php

namespace Scorimmo\Webhook;

use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

class ScorimmoWebhook
{
    private readonly string $headerKey;

    public function __construct(
        private readonly string $headerValue,
        string $headerKey = 'X-Scorimmo-Key',
    ) {
        // Normalize to lowercase for case-insensitive comparison
        $this->headerKey = strtolower($headerKey);
    }

    /**
     * Validates and parses an incoming webhook request.
     *
     * @param array<string, string> $headers  Incoming HTTP headers (case-insensitive)
     * @param string                $rawBody  Raw JSON body
     * @return array<string, mixed>           Parsed event payload
     *
     * @throws WebhookAuthException       On invalid or missing auth header
     * @throws WebhookValidationException On invalid payload
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
     * Dispatches a parsed event to the appropriate handler callable.
     *
     * @param array<string, mixed>    $event    Parsed webhook payload (from parse())
     * @param array<string, callable> $handlers Map of event name => callable
     *
     * Supported keys: new_lead, update_lead, new_comment, new_rdv, new_reminder, closure_lead, unknown
     */
    public function dispatch(array $event, array $handlers): void
    {
        $eventName = $event['event'] ?? 'unknown';

        $handler = $handlers[$eventName] ?? $handlers['unknown'] ?? null;

        if ($handler !== null) {
            $handler($event);
        }
    }

    /**
     * Parses and dispatches a webhook from raw request data (convenience method).
     *
     * @param array<string, string> $headers
     * @param string                $rawBody
     * @param array<string, callable> $handlers
     */
    public function handle(array $headers, string $rawBody, array $handlers): void
    {
        $event = $this->parse($headers, $rawBody);
        $this->dispatch($event, $handlers);
    }

    /**
     * @param array<string, string> $headers
     * @throws WebhookAuthException
     */
    private function assertAuth(array $headers): void
    {
        // Normalize all header keys to lowercase
        $normalized = array_change_key_case($headers, CASE_LOWER);
        $received = $normalized[$this->headerKey] ?? null;

        if ($received !== $this->headerValue) {
            throw new WebhookAuthException('Invalid or missing webhook authentication header');
        }
    }
}
