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
    protected function basePath(): string
    {
        return '/api/v2/leads';
    }

    /**
     * Récupère un lead unique par son identifiant.
     *
     * @param  string[] $include  Relations à charger en même temps :
     *                            'customer', 'seller', 'appointments', 'reminders', 'requests', 'comments'
     * @return array<string, mixed>
     */
    public function get(int $id, array $include = []): array
    {
        $query = [];
        if (!empty($include)) {
            $query['include'] = implode(',', $include);
        }
        return parent::get($id, $query);
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
     *   requests_reference?:    string,
     *   ids?:                   string,
     *   'created_at[eq]'?:      string,
     *   'created_at[gt]'?:      string,
     *   'created_at[gte]'?:     string,
     *   'created_at[lt]'?:      string,
     *   'created_at[lte]'?:     string,
     *   'updated_at[eq]'?:      string,
     *   'updated_at[gt]'?:      string,
     *   'updated_at[gte]'?:     string,
     *   'updated_at[lt]'?:      string,
     *   'updated_at[lte]'?:     string,
     * } $query
     *
     * Tri disponible (paramètre 'sort') : id, created_at, updated_at, status
     * Format : 'champ:asc' ou 'champ:desc' — ex: 'sort' => 'created_at:desc'
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(array $query = []): array
    {
        return parent::list($query);
    }

    /**
     * Mise à jour partielle d'un lead (seuls les champs transmis sont modifiés).
     *
     * Champs courants : external_lead_id, external_customer_id.
     *
     * @param  array<string, mixed> $data  Champs à modifier
     * @return array<string, mixed>        Lead mis à jour
     */
    public function update(int $id, array $data): array
    {
        return $this->client->request('PATCH', $this->basePath() . "/{$id}", $data);
    }

    /**
     * Récupère tous les leads créés ou modifiés après une date donnée.
     * Gère automatiquement la pagination et retourne un tableau à plat dédupliqué.
     *
     * @param  string|\DateTimeInterface $date      Borne inférieure exclusive
     * @param  string                    $field      Champ de date à filtrer : 'created_at' ou 'updated_at'
     * @param  int                       $maxPages   Nombre maximum de pages à récupérer (défaut 100 = 5 000 leads)
     * @param  int|null                  $storeId    Restreindre à un point de vente spécifique ; null = tous
     * @param  string[]                  $include    Relations à charger (ex: ['customer', 'seller'])
     * @return array<int, array<string, mixed>>
     */
    public function since(
        string|\DateTimeInterface $date,
        string $field = 'created_at',
        int $maxPages = 100,
        ?int $storeId = null,
        array $include = [],
    ): array {
        $iso = $date instanceof \DateTimeInterface
            ? $date->format(\DateTimeInterface::ATOM)
            : $date;

        $allLeads = [];
        $page     = 1;

        do {
            $query = [
                "{$field}[gt]" => $iso,
                'sort'         => "{$field}:asc",
                'limit'        => 50,
                'page'         => $page,
            ];

            if ($storeId !== null) {
                $query['store_id'] = $storeId;
            }

            if (!empty($include)) {
                $query['include'] = implode(',', $include);
            }

            $result     = $this->list($query);
            $results    = $result['data'] ?? [];
            $totalItems = $result['meta']['total_items'] ?? 0;
            $allLeads   = array_merge($allLeads, $results);
            $page++;

            // Arrêt si : toutes les pages récupérées, page vide, ou plafond de sécurité atteint
        } while (count($allLeads) < $totalItems && count($results) > 0 && $page <= $maxPages);

        // Déduplique par id — un lead peut apparaître sur deux pages consécutives si la liste
        // se décale pendant la pagination (ex: nouveau lead créé entre deux appels).
        return array_values(array_column($allLeads, null, 'id'));
    }
}
