<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Client\CampaignContactsResource;
use Scorimmo\Client\CampaignFieldsResource;
use Scorimmo\Client\CampaignsResource;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;

class CampaignResourcesTest extends TestCase
{
    public function testCampaignsListSendsFilters(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('GET', '/api/v2/campaigns?status=open&sort=start%3Adesc')
            ->willReturn(['data' => [], 'meta' => []]);

        (new CampaignsResource($client))->list(['status' => 'open', 'sort' => 'start:desc']);
    }

    public function testCampaignsListRejectsUnknownStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CampaignsResource($this->createMock(ScorimmoClient::class)))->list(['status' => 'running']);
    }

    public function testCampaignFieldsListUsesFieldsPath(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('GET', '/api/v2/campaigns/fields?campaign_id=12')
            ->willReturn(['data' => [], 'meta' => []]);

        (new CampaignFieldsResource($client))->list(['campaign_id' => 12]);
    }

    public function testContactsListRequiresCampaignId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CampaignContactsResource($this->createMock(ScorimmoClient::class)))->list(['status' => 'new']);
    }

    public function testContactsListKeepsBracketDateFilters(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('GET', '/api/v2/campaigns/contacts?campaign_id=12&last_contact_at[gte]=2026-11-01')
            ->willReturn(['data' => [], 'meta' => []]);

        (new CampaignContactsResource($client))->list(['campaign_id' => 12, 'last_contact_at[gte]' => '2026-11-01']);
    }

    public function testContactsListRejectsUnknownSubStatus(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CampaignContactsResource($this->createMock(ScorimmoClient::class)))
            ->list(['campaign_id' => 12, 'sub_status' => 'other']);
    }

    public function testInjectPostsCampaignAndContacts(): void
    {
        $contacts = [['last_name' => 'Dupont', 'phone' => '0612345678']];
        $report   = ['data' => [['index' => 0, 'status' => 'created', 'id' => 1]], 'meta' => ['received' => 1, 'created' => 1, 'rejected' => 0]];

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('POST', '/api/v2/campaigns/contacts', ['campaign_id' => 12, 'contacts' => $contacts])
            ->willReturn($report);

        $this->assertSame($report, (new CampaignContactsResource($client))->inject(12, $contacts));
    }

    public function testInjectRejectsEmptyAndOversizedBatches(): void
    {
        $resource = new CampaignContactsResource($this->createMock(ScorimmoClient::class));

        foreach ([[], array_fill(0, 101, ['last_name' => 'A', 'phone' => '0']) ] as $contacts) {
            try {
                $resource->inject(12, $contacts);
                $this->fail('Expected InvalidArgumentException');
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('between 1 and 100', $e->getMessage());
            }
        }
    }

    public function testInjectReturnsReportWhenEveryContactIsRejected(): void
    {
        $report = ['data' => [['index' => 0, 'status' => 'rejected', 'errors' => [['field' => 'phone', 'message' => 'x']]]], 'meta' => ['received' => 1, 'created' => 0, 'rejected' => 1]];

        $client = $this->createMock(ScorimmoClient::class);
        $client->method('request')->willThrowException(new ScorimmoApiException('HTTP 422', 422, null, $report));

        $this->assertSame($report, (new CampaignContactsResource($client))->inject(12, [['last_name' => 'A', 'phone' => 'x']]));
    }

    public function testInjectRethrowsOtherValidationErrors(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->method('request')->willThrowException(new ScorimmoApiException('Campaign closed', 422, 'UNPROCESSABLE_ENTITY', ['message' => 'Campaign closed']));

        $this->expectException(ScorimmoApiException::class);
        (new CampaignContactsResource($client))->inject(12, [['last_name' => 'A', 'phone' => '0612345678']]);
    }
}
