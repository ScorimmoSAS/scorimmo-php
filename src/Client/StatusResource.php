<?php

namespace Scorimmo\Client;

/**
 * Ressource Status — référentiel des statuts et sous-statuts disponibles.
 *
 * Endpoints couverts :
 *  GET  /api/v2/status   → list()
 *
 * Retourne la liste paginée des statuts avec leurs sous-statuts associés.
 * Exemple de réponse : [{ "label": "Succès", "sub_status": ["Loué", "Mandat"] }, ...]
 */
class StatusResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/status';
    }
}
