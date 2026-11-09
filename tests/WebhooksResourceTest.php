<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Client\WebhooksResource;

class WebhooksResourceTest extends TestCase
{
    public function testListRejectsUnknownSortField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WebhooksResource($this->createMock(ScorimmoClient::class)))->list(['sort' => 'url:asc']);
    }

    public function testErrorsUsesErrorsPath(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('GET', '/api/v2/webhooks/errors?status=500&page=2')
            ->willReturn(['data' => [], 'meta' => []]);

        (new WebhooksResource($client))->errors(['status' => 500, 'page' => 2]);
    }

    public function testErrorsValidatesPagination(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WebhooksResource($this->createMock(ScorimmoClient::class)))->errors(['limit' => 101]);
    }

    public function testCreatePostsPayload(): void
    {
        $payload = ['store_id' => 3, 'name' => 'CRM', 'url' => 'https://crm.test/hook', 'events' => ['CAMPAIGN_CLOSED']];

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', '/api/v2/webhooks', $payload)
            ->willReturn(['id' => 9]);

        $this->assertSame(['id' => 9], (new WebhooksResource($client))->create($payload));
    }

    public function testCreateRequiresEvents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('"events"');
        (new WebhooksResource($this->createMock(ScorimmoClient::class)))
            ->create(['store_id' => 3, 'name' => 'CRM', 'url' => 'https://crm.test/hook']);
    }

    public function testUpdatePatchesWebhook(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('PATCH', '/api/v2/webhooks/9', ['name' => 'Nouveau'])
            ->willReturn(['id' => 9]);

        (new WebhooksResource($client))->update(9, ['name' => 'Nouveau']);
    }

    public function testUpdateRejectsEmptyPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new WebhooksResource($this->createMock(ScorimmoClient::class)))->update(9, []);
    }

    public function testDeleteSendsDelete(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('DELETE', '/api/v2/webhooks/9')
            ->willReturn([]);

        (new WebhooksResource($client))->delete(9);
    }
}
