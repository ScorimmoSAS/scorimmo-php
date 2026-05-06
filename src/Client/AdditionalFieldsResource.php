<?php

namespace Scorimmo\Client;

/**
 * Ressource AdditionalFields — champs additionnels configurés par point de vente et intérêt.
 *
 * Endpoints couverts :
 *  GET  /api/v2/additional_fields   → list()
 *
 * Retourne les champs complémentaires qu'il est possible de renseigner sur un lead
 * (au-delà des champs standards). Utiliser les `label` retournés comme clés dans le
 * tableau `additional_fields` de POST /api/v2/form.
 * Pour les champs de type `choice`, seules les valeurs de `choices` sont acceptées.
 *
 * Paramètres de list() :
 *  - store_id  (int)    : filtrer par point de vente
 *  - interest  (string) : filtrer par intérêt (ex: 'Location')
 *
 * Scope requis : ref:read
 */
class AdditionalFieldsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/additional_fields';
    }
}
