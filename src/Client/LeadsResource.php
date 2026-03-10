<?php

namespace Scorimmo\Client;

class LeadsResource
{
    public function __construct(private readonly ScorimmoClient $client) {}

    /**
     * Fetch a single lead by ID.
     *
     * @return array<string, mixed>
     */
    public function get(int $id): array
    {
        return $this->client->request('GET', "/api/lead/{$id}");
    }

    /**
     * List leads with optional filtering, sorting and pagination.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', '/api/leads' . ($qs ? "?{$qs}" : ''));
    }

    /**
     * Fetch all leads created or updated after a given date.
     * Automatically handles pagination and returns a flat array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function since(string|\DateTimeInterface $date, string $field = 'created_at'): array
    {
        $iso = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d H:i:s')
            : $date;

        $allLeads = [];
        $page = 1;

        do {
            $result = $this->list([
                'search'  => [$field => ">{$iso}"],
                'order'   => 'asc',
                'orderby' => $field,
                'limit'   => 50,
                'page'    => $page,
            ]);

            $results = $result['results'] ?? [];
            $total   = $result['total'] ?? 0;
            $allLeads = array_merge($allLeads, $results);
            $page++;
        } while (count($allLeads) < $total && count($results) > 0);

        return $allLeads;
    }

    /**
     * List leads for a specific store.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function listByStore(int $storeId, array $query = []): array
    {
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', "/api/stores/{$storeId}/leads" . ($qs ? "?{$qs}" : ''));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function buildQueryString(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($key === 'search' && is_array($value)) {
                foreach ($value as $searchKey => $searchValue) {
                    $parts[] = 'search[' . urlencode($searchKey) . ']=' . urlencode($searchValue);
                }
            } else {
                $parts[] = urlencode($key) . '=' . urlencode((string) $value);
            }
        }

        return implode('&', $parts);
    }
}
