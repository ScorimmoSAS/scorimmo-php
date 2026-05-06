<?php

namespace Scorimmo\Client;

/**
 * Ressource Reminders — rappels et relances liés aux leads.
 *
 * Endpoints couverts :
 *  GET  /api/v2/reminders        → list()
 *  GET  /api/v2/reminders/{id}   → get()
 *
 * Filtres disponibles : lead_id, store_id, canceled, created_at[gt|gte|lt|lte|eq]
 * Tri disponible      : id, created_at, start_time
 */
class RemindersResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/reminders';
    }
}
