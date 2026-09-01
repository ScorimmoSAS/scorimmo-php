<?php

namespace Scorimmo\Client;

/**
 * Ressource Customers — contacts/prospects rattachés aux leads.
 *
 * Endpoints couverts :
 *  GET  /api/v2/customers        → list()
 *  GET  /api/v2/customers/{id}   → get()
 *
 * @scope ROLE_API_REF_READ (ref:read)
 */
class CustomersResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id'];

    protected function basePath(): string
    {
        return '/api/v2/customers';
    }

    /**
     * Liste les contacts avec filtrage optionnel.
     *
     * Le filtre `phone` s'applique en OR sur les colonnes phone et other_phone côté API.
     *
     * @param array{
     *   page?:   int,
     *   limit?:  int,
     *   sort?:   string,
     *   search?: string,
     *   email?:  string,
     *   phone?:  string,
     * } $query
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
