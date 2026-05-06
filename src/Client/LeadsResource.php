<?php

namespace Scorimmo\Client;

/**
 * Ressource Leads — accès aux demandes de contact (mandats, acheteurs, locataires…).
 *
 * Endpoints couverts :
 *  GET    /api/v2/leads          → list()
 *  GET    /api/v2/leads/{id}     → get()
 *  PATCH  /api/v2/leads/{id}     → update()
 */
class LeadsResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id', 'created_at', 'updated_at', 'status'];

    /** @var string[] Champs de date acceptés dans since() */
    private const DATE_FIELDS = ['created_at', 'updated_at'];

    protected function basePath(): string
    {
        return '/api/v2/leads';
    }

    /**
     * Récupère un lead unique par son identifiant.
     *
     * @param  string[] $query  Relations à charger :
     *                          'customer', 'seller', 'appointments', 'reminders', 'requests', 'comments'
     * @return array<string, mixed>
     */
    public function get(int $id, array $query = []): array
    {
        $params = [];
        if (!empty($query)) {
            $params['include'] = implode(',', $query);
        }
        return parent::get($id, $params);
    }

    /**
     * Liste les leads avec filtrage, tri et pagination.
     *
     * @param array{
     *   page?:                  int,
     *   limit?:                 int,
     *   sort?:                  string,
     *   include?:               string,
     *   store_id?:              int,
     *   seller_id?:             int,
     *   status?:                string,
     *   substatus?:             string,
     *   interest?:              string,
     *   origin?:                string,
     *   contact_type?:          'physical'|'phone'|'digital',
     *   purpose?:               string,
     *   customer_first_name?:   string,
     *   customer_last_name?:    string,
     *   'customer.email'?:      string,
     *   'customer.phone'?:      string,
     *   external_lead_id?:      string,
     *   requests_reference?:    string,
     *   ids?:                   string,
     *   'created_at[eq]'?:      string,
     *   'created_at[gte]'?:     string,
     *   'created_at[lte]'?:     string,
     *   'updated_at[eq]'?:      string,
     *   'updated_at[gte]'?:     string,
     *   'updated_at[lte]'?:     string,
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort, limit ou page ont une valeur invalide
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        return parent::list($query);
    }

    /**
     * Mise à jour partielle d'un lead (seuls les champs transmis sont modifiés).
     *
     * @param  array<string, mixed> $data  Champs à modifier
     * @return array<string, mixed>        Lead mis à jour
     */
    public function update(int $id, array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('update() requires at least one field to modify');
        }

        return $this->client->request('PATCH', $this->basePath() . "/{$id}", $data);
    }

    /**
     * Récupère tous les leads créés ou modifiés après une date donnée.
     * Gère automatiquement la pagination et retourne un tableau à plat dédupliqué.
     *
     * @param  string|\DateTimeInterface $date
     *           - DateTimeInterface : l'heure est préservée telle quelle
     *           - string            : Y-m-d (ex: "2026-05-01"), Y-m-d H:i:s,
     *                                 ou ISO 8601 (ex: "2026-05-01T12:00:00+02:00")
     * @param  string        $field      Champ de date : 'created_at' (défaut) ou 'updated_at'
     * @param  int           $maxPages   Nombre max de pages (défaut 100 ≈ 10 000 leads)
     * @param  int|null      $storeId    Restreindre à un point de vente ; null = tous
     * @param  string[]      $include    Relations à charger (ex: ['customer', 'seller'])
     * @param  callable|null $onProgress Callback après chaque page : fn(int $page, int $count, int $total, array $meta)
     *
     * @return array<int, array<string, mixed>>
     * @throws \InvalidArgumentException Si date, field ou maxPages ont une valeur invalide
     */
    public function since(
        string|\DateTimeInterface $date,
        string $field = 'created_at',
        int $maxPages = 100,
        ?int $storeId = null,
        array $include = [],
        ?callable $onProgress = null,
    ): array {
        if (!in_array($field, self::DATE_FIELDS, true)) {
            throw new \InvalidArgumentException(
                sprintf('"field" must be one of: %s. Got: "%s"', implode(', ', self::DATE_FIELDS), $field)
            );
        }

        if ($maxPages < 1) {
            throw new \InvalidArgumentException(
                sprintf('"maxPages" must be >= 1, got: %d', $maxPages)
            );
        }

        if ($date instanceof \DateTimeInterface) {
            $iso = $date->format('Y-m-d H:i:s');
        } else {
            // Accepte : Y-m-d | Y-m-d H:i:s | ISO 8601 avec/sans timezone (T ou espace, Z ou ±HH:MM)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?([+-]\d{2}:?\d{2}|Z)?)?$/', $date)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        '"date" string must be Y-m-d, Y-m-d H:i:s, or ISO 8601 ' .
                        '(e.g. "2026-05-01", "2026-05-01 12:00:00", "2026-05-01T12:00:00+02:00"), got: "%s".',
                        $date
                    )
                );
            }
            $iso = $date;
        }

        $allLeads = [];
        $page     = 1;

        do {
            $query = [
                "{$field}[gte]" => $iso,
                'sort'          => "{$field}:asc",
                'limit'         => 100,
                'page'          => $page,
            ];

            if ($storeId !== null) {
                $query['store_id'] = $storeId;
            }

            if (!empty($include)) {
                $query['include'] = implode(',', $include);
            }

            $result   = $this->list($query);
            $results  = $result['data'] ?? [];
            $allLeads = array_merge($allLeads, $results);

            if ($onProgress !== null) {
                ($onProgress)($page, count($results), count($allLeads), $result['meta'] ?? []);
            }

            $page++;

        } while (isset($result['meta']['next_page']) && count($results) > 0 && $page <= $maxPages);

        // Déduplique par id — un lead peut apparaître sur deux pages consécutives si la liste
        // se décale pendant la pagination (ex: nouveau lead créé entre deux appels).
        return array_values(array_column($allLeads, null, 'id'));
    }
}
