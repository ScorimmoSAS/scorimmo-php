<?php

namespace Scorimmo\Client;

/**
 * Ressource Origins — origines configurées sur le compte.
 *
 * Endpoints couverts :
 *  GET  /api/v2/origins   → list()
 *
 * Utiliser le champ `label` retourné comme valeur du filtre `origin` dans leads->list()
 * et comme valeur du paramètre `origin` dans POST /api/v2/form.
 *
 * Paramètres de list() :
 *  - store_id        (int)    : filtrer par point de vente
 *  - has_tracking    (bool)   : ne retourner que les origines ayant au moins un traceur actif
 *  - tracking_channel (string): 'phone' ou 'email'
 *  - include         (string) : 'tracking' pour inclure les traceurs de chaque origine
 *
 * Scope requis : ref:read
 */
class OriginsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/origins';
    }
}
