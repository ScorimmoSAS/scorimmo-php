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
     * @param array{
     *   search?:  array<string, string>,
     *   orderby?: 'id'|'created_at'|'updated_at'|'status'|'seller_id'|'closed_date'|'interest'|'origin',
     *   order?:   'asc'|'desc',
     *   limit?:   int,
     *   page?:    int,
     * } $query
     *
     * Available search keys: id, created_at, updated_at, status, closed_date, anonymized_at,
     * interest, origin, customer_firstname, customer_lastname, email, phone, other_phone_number,
     * seller_id, seller_firstname, seller_lastname, reference, external_lead_id, external_customer_id,
     * seller_present_on_creation, transfered.
     * Prefix value with >, >=, < or <= for comparisons (e.g. 'created_at' => '>=2026-01-01 00:00:00').
     * No prefix means strict equality.
     *
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
     * @param  int|null $storeId  Restrict to a specific store (uses /api/stores/{id}/leads); null = global
     * @param  int      $maxPages Safety cap on the number of API pages fetched (default 100 → 5 000 leads)
     * @return array<int, array<string, mixed>>
     */
    public function since(string|\DateTimeInterface $date, string $field = 'created_at', int $maxPages = 100, ?int $storeId = null): array
    {
        $iso = $date instanceof \DateTimeInterface
            ? $date->format('Y-m-d H:i:s')
            : $date;

        $allLeads = [];
        $page = 1;

        do {
            $query = [
                'search'  => [$field => ">{$iso}"],
                'order'   => 'asc',
                'orderby' => $field,
                'limit'   => 50,
                'page'    => $page,
            ];

            $result = $storeId !== null
                ? $this->listByStore($storeId, $query)
                : $this->list($query);

            $results    = $result['results'] ?? [];
            $totalItems = $result['informations'][0]['informations']['total_items'] ?? 0;
            $allLeads   = array_merge($allLeads, $results);
            $page++;
        } while (count($allLeads) < $totalItems && count($results) > 0 && $page <= $maxPages);

        // Deduplicate by id — a lead can appear on two consecutive pages if it is
        // created or updated while pagination is in progress (boundary shift).
        return array_values(array_column($allLeads, null, 'id'));
    }

    /**
     * List leads for a specific store.
     * Accepts the same $query parameters as {@see list()}.
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
