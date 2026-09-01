<?php

namespace Scorimmo\Client;

/**
 * Ressource Requests — biens/propriétés recherchés ou proposés dans un lead.
 *
 * Endpoints couverts :
 *  GET  /api/v2/requests        → list()
 *  GET  /api/v2/requests/{id}   → get()
 *
 * Filtres disponibles : lead_id, store_id, type, created_at[gte|lte|eq]
 * Tri disponible      : id, created_at
 *
 * @scope ROLE_API_LEAD_READ (lead:read)
 */
class RequestsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/requests';
    }
}
