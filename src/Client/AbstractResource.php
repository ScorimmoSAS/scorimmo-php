<?php

namespace Scorimmo\Client;

/**
 * Classe de base pour toutes les ressources de l'API Scorimmo v2.
 *
 * Fournit les opérations CRUD génériques (get, list) communes à toutes les ressources.
 * Chaque sous-classe déclare simplement son chemin de base via basePath().
 */
abstract class AbstractResource
{
    public function __construct(protected readonly ScorimmoClient $client) {}

    /**
     * Récupère une ressource unique par son identifiant.
     *
     * @param  array<string, scalar> $query  Paramètres de requête additionnels (ex: ['include' => 'customer'])
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
     * Paramètres communs à toutes les ressources :
     *  - page  (int, défaut 1)
     *  - limit (int, défaut 10, max 100)
     *  - sort  (string, ex: 'created_at:desc')
     *
     * @param  array<string, scalar> $query
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function list(array $query = []): array
    {
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', $this->basePath() . ($qs ? "?{$qs}" : ''));
    }

    /**
     * Retourne le chemin de base de la ressource dans l'API v2 (ex: '/api/v2/leads').
     */
    abstract protected function basePath(): string;

    /**
     * Encode un tableau clé-valeur en query string URL.
     *
     * Les valeurs null sont ignorées.
     * La notation bracket dans les clés (ex: 'created_at[gte]') est préservée et encodée
     * correctement via http_build_query, ce qui produit le format attendu par Symfony.
     *
     * @param array<string, scalar|null> $query
     */
    protected function buildQueryString(array $query): string
    {
        $filtered = array_filter($query, fn($v) => $v !== null);
        return http_build_query($filtered);
    }
}
