<?php

namespace Scorimmo\Client;

/**
 * Ressource Users — conseillers et managers des points de vente accessibles.
 *
 * Endpoints couverts :
 *  GET  /api/v2/users        → list()
 *  GET  /api/v2/users/{id}   → get()
 *
 * Chaque utilisateur inclut son rôle ('admin', 'manager', 'agent'),
 * ses scopes API et les stores auxquels il est rattaché.
 * Tri disponible : id, last_name
 */
class UsersResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/users';
    }
}
