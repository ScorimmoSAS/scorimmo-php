<?php

namespace Scorimmo\Client;

/**
 * Ressource Comments — commentaires et notes liés aux leads.
 *
 * Endpoints couverts :
 *  GET  /api/v2/comments        → list()
 *  GET  /api/v2/comments/{id}   → get()
 *
 * Filtres disponibles : lead_id, store_id, user_id, breadcrumb, created_at[gt|gte|lt|lte|eq]
 * Tri disponible      : id, created_at
 */
class CommentsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/comments';
    }
}
