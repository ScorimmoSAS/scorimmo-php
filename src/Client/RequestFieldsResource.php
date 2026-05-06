<?php

namespace Scorimmo\Client;

/**
 * Ressource RequestFields — champs de demande configurés par point de vente et intérêt.
 *
 * Endpoints couverts :
 *  GET  /api/v2/requests/fields   → list()
 *
 * Retourne les critères de recherche immobilière attendus dans le tableau `requests`
 * de POST /api/v2/form. Utiliser les `label` retournés comme clés dans chaque objet
 * du tableau `requests`.
 * Pour les champs de type `choice`, seules les valeurs de `choices` sont acceptées.
 *
 * Paramètres de list() :
 *  - store_id  (int)    : filtrer par point de vente
 *  - interest  (string) : filtrer par intérêt (ex: 'Location')
 *
 * Scope requis : ref:read
 */
class RequestFieldsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/requests/fields';
    }
}
