<?php

namespace Scorimmo\Client;

/**
 * Ressource Users — conseillers et managers des points de vente accessibles.
 *
 * Endpoints couverts :
 *  GET  /api/v2/users        → list()
 *  GET  /api/v2/users/{id}   → get()
 *
 * Chaque utilisateur inclut son rôle ('admin', 'manager', 'agent', 'virtual'),
 * ses intérêts et son statut is_virtual.
 * @scope ROLE_API_REF_READ (ref:read)
 */
class UsersResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id', 'last_name', 'created_at'];

    /** @var string[] Rôles acceptés */
    private const ROLES = ['admin', 'manager', 'agent', 'virtual'];

    protected function basePath(): string
    {
        return '/api/v2/users';
    }

    /**
     * Liste les utilisateurs avec filtrage optionnel.
     *
     * @param array{
     *   page?:      int,
     *   limit?:     int,
     *   sort?:      string,
     *   store_id?:  int,
     *   interest?:  string,
     *   role?:      'admin'|'manager'|'agent'|'virtual',
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort ou role ont une valeur invalide
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        if (isset($query['role']) && !in_array($query['role'], self::ROLES, true)) {
            throw new \InvalidArgumentException(
                sprintf('"role" must be one of: %s. Got: "%s"', implode(', ', self::ROLES), (string) $query['role'])
            );
        }

        return parent::list($query);
    }
}
