<?php

namespace Scorimmo\Client;

/**
 * Ressource Customers — contacts/prospects rattachés aux leads.
 *
 * Endpoints couverts :
 *  GET  /api/v2/customers        → list()
 *  GET  /api/v2/customers/{id}   → get()
 *
 * Filtres disponibles : email, phone, zip_code, city
 * Tri disponible      : id, last_name, created_at
 */
class CustomersResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/customers';
    }
}
