<?php

namespace Scorimmo\Client;

/**
 * Ressource Appointments — rendez-vous liés aux leads.
 *
 * Endpoints couverts :
 *  GET  /api/v2/appointments        → list()
 *  GET  /api/v2/appointments/{id}   → get()
 *
 * Filtres disponibles : lead_id, store_id, canceled, created_at[gt|gte|lt|lte|eq]
 * Tri disponible      : id, created_at, start_time
 */
class AppointmentsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/appointments';
    }
}
