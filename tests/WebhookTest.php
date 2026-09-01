<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Scorimmo\Webhook\ScorimmoWebhook;

class WebhookTest extends TestCase
{
    private const HMAC_SECRET = 'shared-secret-abc';

    private string $newLeadPayload;

    protected function setUp(): void
    {
        $this->newLeadPayload = json_encode([
            'event'      => 'new_lead',
            'id'         => 42,
            'store_id'   => 1,
            'created_at' => '2026-06-01 10:00:00',
            'interest'   => 'TRANSACTION',
            'customer'   => ['first_name' => 'Jean', 'last_name' => 'Dupont'],
        ]);
    }

    private function signature(string $body, string $secret = self::HMAC_SECRET): string
    {
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }

    // ── Construction ──────────────────────────────────────────────────────────────

    public function testConstructorAcceptsNoSecret(): void
    {
        $webhook = new ScorimmoWebhook();
        $this->assertFalse($webhook->verifiesSignature());
    }

    public function testConstructorAcceptsHmacSecret(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $this->assertTrue($webhook->verifiesSignature());
    }

    public function testEmptyStringSecretIsTreatedAsUnverified(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: '');
        $this->assertFalse($webhook->verifiesSignature());
    }

    // ── Signature disabled (no secret) ────────────────────────────────────────────

    public function testParsesWithoutVerifyingWhenNoSecret(): void
    {
        $webhook = new ScorimmoWebhook();
        // Aucun header de signature envoyé — accepté quand même
        $event = $webhook->parse([], $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
        $this->assertSame(42, $event['id']);
    }

    public function testStillValidatesPayloadWhenNoSecret(): void
    {
        $webhook = new ScorimmoWebhook();
        $this->expectException(WebhookValidationException::class);
        $webhook->parse([], 'not-json');
    }

    // ── HMAC signature enabled ────────────────────────────────────────────────────

    public function testParsesValidHmacSignedPayload(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => $this->signature($this->newLeadPayload)];

        $event = $webhook->parse($headers, $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
        $this->assertSame(42, $event['id']);
    }

    public function testRejectsInvalidHmacSignature(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => 'sha256=deadbeef'];

        $this->expectException(WebhookAuthException::class);
        $webhook->parse($headers, $this->newLeadPayload);
    }

    public function testRejectsMissingSignatureHeader(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);

        $this->expectException(WebhookAuthException::class);
        $webhook->parse([], $this->newLeadPayload);
    }

    public function testAcceptsCustomSignatureHeader(): void
    {
        $webhook = new ScorimmoWebhook(
            signatureSecret: self::HMAC_SECRET,
            signatureHeader: 'X-Custom-Sig',
        );
        $headers = ['x-custom-sig' => $this->signature($this->newLeadPayload)];

        $event = $webhook->parse($headers, $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
    }

    public function testAcceptsSignatureWithoutSha256Prefix(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $raw     = hash_hmac('sha256', $this->newLeadPayload, self::HMAC_SECRET);
        $headers = ['x-signature-256' => $raw];

        $event = $webhook->parse($headers, $this->newLeadPayload);
        $this->assertSame('new_lead', $event['event']);
    }

    public function testAcceptsHeaderValueAsArray(): void
    {
        // Symfony HeaderBag::all() renvoie array<string, string[]>
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => [$this->signature($this->newLeadPayload)]];

        $event = $webhook->parse($headers, $this->newLeadPayload);
        $this->assertSame(42, $event['id']);
    }

    public function testVerifySignaturePublicHelper(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $sig     = $this->signature($this->newLeadPayload);

        $this->assertTrue($webhook->verifySignature($this->newLeadPayload, $sig, self::HMAC_SECRET));
        $this->assertTrue($webhook->verifySignature($this->newLeadPayload, substr($sig, 7), self::HMAC_SECRET));
        $this->assertFalse($webhook->verifySignature($this->newLeadPayload, 'sha256=nope', self::HMAC_SECRET));
        $this->assertFalse($webhook->verifySignature($this->newLeadPayload . 'tampered', $sig, self::HMAC_SECRET));
    }

    // ── Payload validation ────────────────────────────────────────────────────────

    public function testThrowsOnInvalidJson(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => $this->signature('not-json')];

        $this->expectException(WebhookValidationException::class);
        $webhook->parse($headers, 'not-json');
    }

    public function testThrowsOnMissingEventField(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $body    = json_encode(['id' => 1]);
        $headers = ['x-signature-256' => $this->signature($body)];

        $this->expectException(WebhookValidationException::class);
        $webhook->parse($headers, $body);
    }

    // ── Dispatch ──────────────────────────────────────────────────────────────────

    public function testDispatchCallsCorrectHandler(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => $this->signature($this->newLeadPayload)];

        $called = null;
        $event  = $webhook->parse($headers, $this->newLeadPayload);

        $webhook->dispatch($event, [
            'new_lead' => function (array $e) use (&$called) { $called = $e['event']; },
        ]);

        $this->assertSame('new_lead', $called);
    }

    public function testDispatchCallsUnknownHandlerForFutureEvents(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $body    = json_encode(['event' => 'future_event', 'lead_id' => 1]);
        $headers = ['x-signature-256' => $this->signature($body)];

        $called = false;
        $event  = $webhook->parse($headers, $body);
        $webhook->dispatch($event, [
            'unknown' => function () use (&$called) { $called = true; },
        ]);

        $this->assertTrue($called);
    }

    public function testDispatchDoesNotThrowWhenNoHandlerRegistered(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => $this->signature($this->newLeadPayload)];

        $event = $webhook->parse($headers, $this->newLeadPayload);
        $webhook->dispatch($event, []);
        $this->assertTrue(true); // no exception
    }

    public function testHandleConvenienceMethod(): void
    {
        $webhook = new ScorimmoWebhook(signatureSecret: self::HMAC_SECRET);
        $headers = ['x-signature-256' => $this->signature($this->newLeadPayload)];

        $received = null;
        $webhook->handle($headers, $this->newLeadPayload, [
            'new_lead' => function (array $e) use (&$received) { $received = $e['id']; },
        ]);

        $this->assertSame(42, $received);
    }

    // ── Header helpers ────────────────────────────────────────────────────────────

    public function testGetSemanticEventReadsHeader(): void
    {
        $webhook = new ScorimmoWebhook();
        $this->assertSame('lead.created', $webhook->getSemanticEvent(['X-Scorimmo-Event' => 'lead.created']));
        $this->assertSame('webhook.some_future', $webhook->getSemanticEvent(['x-scorimmo-event' => 'webhook.some_future']));
        $this->assertNull($webhook->getSemanticEvent([]));
    }

    public function testGetApiVersionReadsHeader(): void
    {
        $webhook = new ScorimmoWebhook();
        $this->assertSame('2026-04-20', $webhook->getApiVersion(['X-Scorimmo-Version' => '2026-04-20']));
        $this->assertNull($webhook->getApiVersion([]));
    }
}
