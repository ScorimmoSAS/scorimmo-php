<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

class WebhookTest extends TestCase
{
    private ScorimmoWebhook $webhook;
    private array $validHeaders;
    private string $newLeadPayload;

    protected function setUp(): void
    {
        $this->webhook = new ScorimmoWebhook('super-secret', 'X-Api-Key');
        $this->validHeaders = ['x-api-key' => 'super-secret'];
        $this->newLeadPayload = json_encode([
            'event'      => 'new_lead',
            'id'         => 42,
            'store_id'   => 1,
            'created_at' => '2024-06-01 10:00:00',
            'interest'   => 'TRANSACTION',
            'customer'   => ['first_name' => 'Jean', 'last_name' => 'Dupont'],
        ]);
    }

    public function testParsesValidPayload(): void
    {
        $event = $this->webhook->parse($this->validHeaders, $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
        $this->assertSame(42, $event['id']);
    }

    public function testThrowsOnWrongHeaderValue(): void
    {
        $this->expectException(WebhookAuthException::class);
        $this->webhook->parse(['x-api-key' => 'wrong'], $this->newLeadPayload);
    }

    public function testThrowsOnMissingHeader(): void
    {
        $this->expectException(WebhookAuthException::class);
        $this->webhook->parse([], $this->newLeadPayload);
    }

    public function testThrowsOnInvalidJson(): void
    {
        $this->expectException(WebhookValidationException::class);
        $this->webhook->parse($this->validHeaders, 'not-json');
    }

    public function testThrowsOnMissingEventField(): void
    {
        $this->expectException(WebhookValidationException::class);
        $this->webhook->parse($this->validHeaders, json_encode(['id' => 1]));
    }

    public function testIsCaseInsensitiveOnHeaderKey(): void
    {
        $event = $this->webhook->parse(['X-API-KEY' => 'super-secret'], $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
    }

    public function testDispatchCallsCorrectHandler(): void
    {
        $called = null;
        $event  = $this->webhook->parse($this->validHeaders, $this->newLeadPayload);

        $this->webhook->dispatch($event, [
            'new_lead' => function (array $e) use (&$called) { $called = $e['event']; },
        ]);

        $this->assertSame('new_lead', $called);
    }

    public function testDispatchCallsUnknownHandlerForUnrecognisedEvents(): void
    {
        $called  = false;
        $payload = json_encode(['event' => 'future_event', 'lead_id' => 1]);
        $event   = $this->webhook->parse($this->validHeaders, $payload);

        $this->webhook->dispatch($event, [
            'unknown' => function () use (&$called) { $called = true; },
        ]);

        $this->assertTrue($called);
    }

    public function testDispatchDoesNotThrowWhenNoHandlerRegistered(): void
    {
        $event = $this->webhook->parse($this->validHeaders, $this->newLeadPayload);
        $this->webhook->dispatch($event, []);
        $this->assertTrue(true); // No exception thrown
    }

    public function testHandleConvenienceMethod(): void
    {
        $received = null;

        $this->webhook->handle(
            $this->validHeaders,
            $this->newLeadPayload,
            ['new_lead' => function (array $e) use (&$received) { $received = $e['id']; }]
        );

        $this->assertSame(42, $received);
    }
}
