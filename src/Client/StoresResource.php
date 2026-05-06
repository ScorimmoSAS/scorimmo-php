<?php

namespace Scorimmo\Client;

/**
 * Ressource Stores — points de vente accessibles avec les credentials fournis.
 *
 * Endpoints couverts :
 *  GET  /api/v2/stores        → list()
 *  GET  /api/v2/stores/{id}   → get()
 *
 * L'accès est limité aux stores inclus dans le JWT (champ 'stores').
 * Tri disponible : id, name
 */
class StoresResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/stores';
    }
}
