<?php

namespace Scorimmo\Tests;

use PHPUnit\Framework\TestCase;
use Scorimmo\Client\LeadsResource;
use Scorimmo\Client\ScorimmoClient;

class LeadsResourceTest extends TestCase
{
    /**
     * Construit une réponse paginée au format API v2 : { data: [...], meta: {...} }
     */
    private function makePage(array $leads, int $totalItems, int $page, int $limit = 50): array
    {
        $totalPages = (int) ceil($totalItems / $limit);

        return [
            'data' => $leads,
            'meta' => [
                'limit'         => $limit,
                'current_page'  => $page,
                'total_items'   => $totalItems,
                'total_pages'   => $totalPages,
                'previous_page' => $page > 1 ? $page - 1 : null,
                'next_page'     => $page < $totalPages ? $page + 1 : null,
            ],
        ];
    }

    private function makeLead(int $id): array
    {
        return ['id' => $id, 'first_name' => 'Test', 'last_name' => 'Lead', 'created_at' => '2026-03-01T00:00:00+00:00'];
    }

    public function testSinceFetchesSinglePage(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn($this->makePage([$this->makeLead(1), $this->makeLead(2)], 2, 1));

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00');

        $this->assertCount(2, $leads);
        $this->assertSame(1, $leads[0]['id']);
        $this->assertSame(2, $leads[1]['id']);
    }

    public function testSincePaginatesAcrossMultiplePages(): void
    {
        $page1 = array_map(fn($i) => $this->makeLead($i), range(1, 50));
        $page2 = array_map(fn($i) => $this->makeLead($i), range(51, 100));
        $page3 = [$this->makeLead(101), $this->makeLead(102)];

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->exactly(3))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makePage($page1, 102, 1),
                $this->makePage($page2, 102, 2),
                $this->makePage($page3, 102, 3),
            );

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00');

        $this->assertCount(102, $leads);
        $this->assertSame(1, $leads[0]['id']);
        $this->assertSame(102, $leads[101]['id']);
    }

    public function testSinceStopsWhenResultsIsEmpty(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn($this->makePage([], 0, 1));

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00');

        $this->assertCount(0, $leads);
    }

    public function testSinceRespectsMaxPagesCap(): void
    {
        // total_items = 300 (6 pages de 50) mais maxPages = 2
        $page1 = array_map(fn($i) => $this->makeLead($i), range(1, 50));
        $page2 = array_map(fn($i) => $this->makeLead($i), range(51, 100));

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makePage($page1, 300, 1),
                $this->makePage($page2, 300, 2),
            );

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00', 'created_at', 2);

        $this->assertCount(100, $leads);
    }

    public function testSinceWithRealApiResponseStructure(): void
    {
        // Reproduit la structure réelle de l'API v2 (limit 10, 739 items au total)
        $results = array_map(fn($i) => $this->makeLead($i), range(1, 10));
        $apiResponse = [
            'data' => $results,
            'meta' => [
                'limit'         => 10,
                'current_page'  => 1,
                'total_items'   => 739,
                'total_pages'   => 74,
                'previous_page' => null,
                'next_page'     => 2,
            ],
        ];

        // On plafonne à 1 page pour garder le test rapide
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn($apiResponse);

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00', 'created_at', 1);

        // Vérifie que total_items est bien lu (la boucle continuerait sans le plafond)
        $this->assertCount(10, $leads);
    }

    public function testSinceWithStoreIdFiltersOnStoreId(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            // En v2, le filtre store est un paramètre store_id= sur /api/v2/leads
            ->with('GET', $this->stringContains('store_id=776'))
            ->willReturn($this->makePage([$this->makeLead(1)], 1, 1));

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00', 'created_at', 100, 776);

        $this->assertCount(1, $leads);
    }

    public function testSinceWithoutStoreIdUsesGlobalEndpoint(): void
    {
        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/api/v2/leads'))
            ->willReturn($this->makePage([$this->makeLead(1)], 1, 1));

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00');

        $this->assertCount(1, $leads);
    }

    public function testSinceWithStoreIdPaginates(): void
    {
        $page1 = array_map(fn($i) => $this->makeLead($i), range(1, 50));
        $page2 = [$this->makeLead(51)];

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->exactly(2))
            ->method('request')
            ->with('GET', $this->stringContains('store_id=42'))
            ->willReturnOnConsecutiveCalls(
                $this->makePage($page1, 51, 1),
                $this->makePage($page2, 51, 2),
            );

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00', 'created_at', 100, 42);

        $this->assertCount(51, $leads);
    }

    public function testSinceDeduplicatesLeadsAcrossPages(): void
    {
        // Le lead 50 apparaît sur les deux pages (décalage de liste pendant la pagination)
        $page1 = array_map(fn($i) => $this->makeLead($i), range(1, 50));
        $page2 = array_map(fn($i) => $this->makeLead($i), range(50, 55)); // id 50 dupliqué

        $client = $this->createMock(ScorimmoClient::class);
        $client->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $this->makePage($page1, 55, 1),
                $this->makePage($page2, 55, 2),
            );

        $resource = new LeadsResource($client);
        $leads    = $resource->since('2026-03-01 00:00:00');

        $this->assertCount(55, $leads);

        $ids = array_column($leads, 'id');
        $this->assertSame($ids, array_unique($ids), 'No duplicate ids expected');
    }
}
