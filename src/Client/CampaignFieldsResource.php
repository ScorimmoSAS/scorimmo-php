<?php

namespace Scorimmo\Client;

/**
 * Ressource CampaignFields — questions configurées sur les campagnes.
 *
 * Endpoints couverts :
 *  GET  /api/v2/campaigns/fields   → list()
 *
 * Utiliser les `label` retournés comme clés du tableau `answers` des contacts injectés via
 * CampaignContactsResource::inject().
 *
 * Paramètres de list() :
 *  - campaign_id (int) : filtrer par campagne
 *  - store_id    (int) : filtrer par point de vente
 *
 * @scope ROLE_API_CAMPAIGN_READ (campaign:read)
 */
class CampaignFieldsResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/campaigns/fields';
    }
}
