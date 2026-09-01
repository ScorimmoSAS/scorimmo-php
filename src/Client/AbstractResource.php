<?php

namespace Scorimmo\Client;

/**
 * Classe de base pour toutes les ressources de l'API Scorimmo v2.
 *
 * Fournit les opérations CRUD génériques (get, list) communes à toutes les ressources,
 * la validation des paramètres de pagination et le helper de query string.
 * Chaque sous-classe déclare simplement son chemin de base via basePath().
 */
abstract class AbstractResource
{
    public function __construct(protected readonly ScorimmoClient $client) {}

    /**
     * Récupère une ressource unique par son identifiant.
     *
     * @param  array<string, scalar|null> $query  Paramètres additionnels (ex: ['include' => 'customer'])
     * @return array<string, mixed>
     */
    public function get(int $id, array $query = []): array
    {
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', $this->basePath() . "/{$id}" . ($qs ? "?{$qs}" : ''));
    }

    /**
     * Liste les ressources avec filtrage, tri et pagination optionnels.
     *
     * Paramètres communs : page (int, défaut 1), limit (int, 1–100, défaut 10), sort (string).
     * La validation de limit et page est appliquée automatiquement.
     *
     * @param  array<string, scalar|null> $query
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si limit ou page ont une valeur invalide
     */
    public function list(array $query = []): array
    {
        $this->assertValidPagination($query);
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', $this->basePath() . ($qs ? "?{$qs}" : ''));
    }

    /**
     * Retourne le chemin de base de la ressource dans l'API v2 (ex: '/api/v2/leads').
     */
    abstract protected function basePath(): string;

    /**
     * Valide les paramètres de pagination communs à toutes les ressources.
     *
     * @throws \InvalidArgumentException
     */
    /**
     * @param array<string, mixed> $query
     */
    protected function assertValidPagination(array $query): void
    {
        if (array_key_exists('limit', $query)) {
            $limit = $query['limit'];
            if (!is_int($limit) || $limit < 1 || $limit > 100) {
                throw new \InvalidArgumentException(
                    sprintf('"limit" must be an integer between 1 and 100, got: %s', json_encode($limit))
                );
            }
        }

        if (array_key_exists('page', $query)) {
            $page = $query['page'];
            if (!is_int($page) || $page < 1) {
                throw new \InvalidArgumentException(
                    sprintf('"page" must be a positive integer (>= 1), got: %s', json_encode($page))
                );
            }
        }
    }

    /**
     * Valide un paramètre sort au format "field:direction".
     *
     * @param  string   $sort         Valeur du paramètre sort (ex: 'created_at:desc')
     * @param  string[] $validFields  Champs de tri acceptés pour cette ressource
     * @throws \InvalidArgumentException
     */
    protected function assertValidSort(string $sort, array $validFields): void
    {
        $parts = explode(':', $sort, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                sprintf('"sort" must be in format "field:direction", got: "%s"', $sort)
            );
        }

        [$field, $direction] = $parts;

        if (!in_array($field, $validFields, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    '"sort" field must be one of: %s. Got: "%s"',
                    implode(', ', $validFields),
                    $field
                )
            );
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(
                sprintf('"sort" direction must be "asc" or "desc", got: "%s"', $direction)
            );
        }
    }

    /**
     * Encode un tableau clé-valeur en query string URL.
     *
     * Les valeurs null sont ignorées.
     * La notation bracket dans les clés (ex: 'created_at[gte]') est préservée non encodée
     * afin que le serveur PHP parse correctement les filtres de date.
     *
     * @param array<string, scalar|null> $query
     */
    protected function buildQueryString(array $query): string
    {
        $filtered = array_filter($query, fn($v) => $v !== null);
        return str_replace(['%5B', '%5D'], ['[', ']'], http_build_query($filtered));
    }
}
