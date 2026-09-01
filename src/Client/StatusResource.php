<?php

namespace Scorimmo\Client;

/**
 * Ressource Status — référentiel des statuts et sous-statuts disponibles.
 *
 * Endpoints couverts :
 *  GET  /api/v2/status   → list()
 *
 * Retourne la liste paginée des statuts avec leurs sous-statuts associés.
 * Exemple de réponse : [{ "label": "Succès", "sub_status": ["Loué", "Mandat"] }, ...]
 *
 * Les filtres `interest` et `store_id` acceptent une liste CSV (ex: "TRANSACTION,LOCATION"
 * ou "1,2,3"). Scope requis : ref:read.
 */
class StatusResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id'];

    protected function basePath(): string
    {
        return '/api/v2/status';
    }

    /**
     * Liste les statuts avec filtrage optionnel.
     *
     * @param array{
     *   page?:     int,
     *   limit?:    int,
     *   sort?:     string,
     *   ids?:      string,
     *   interest?: string,
     *   store_id?: string,
     * } $query  interest et store_id acceptent une liste CSV.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort a une valeur invalide
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        return parent::list($query);
    }
}
