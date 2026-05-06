<?php

namespace Scorimmo\Client;

/**
 * Ressource Requests — biens/propriétés recherchés ou proposés dans un lead.
 *
 * En API v2 les biens sont appelés « requests » (vs « properties » en v1).
 *
 * Endpoints couverts :
 *  GET  /api/v2/requests        → list()
 *  GET  /api/v2/requests/{id}   → get()
 *
 * Filtres disponibles : lead_id, store_id, type, created_at[gt|gte|lt|lte|eq]
 * Tri disponible      : id, created_at
 */
class RequestsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/requests';
    }
}
