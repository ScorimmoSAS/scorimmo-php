<?php

namespace Scorimmo\Client;

/**
 * Ressource Campaigns — campagnes d'appels des points de vente accessibles.
 *
 * Endpoints couverts :
 *  GET  /api/v2/campaigns        → list()
 *  GET  /api/v2/campaigns/{id}   → get()
 *
 * Sert à découvrir les identifiants de campagne et à savoir lesquelles sont ouvertes
 * (`status=open`) avant d'y injecter des contacts via CampaignContactsResource.
 *
 * @scope ROLE_API_CAMPAIGN_READ (campaign:read)
 */
class CampaignsResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id', 'name', 'start', 'end'];

    /** @var string[] Statuts acceptés */
    private const STATUSES = ['upcoming', 'open', 'closed'];

    protected function basePath(): string
    {
        return '/api/v2/campaigns';
    }

    /**
     * Liste les campagnes avec filtrage optionnel.
     *
     * @param array{
     *   page?:     int,
     *   limit?:    int,
     *   sort?:     string,
     *   ids?:      string,
     *   store_id?: int,
     *   status?:   'upcoming'|'open'|'closed',
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort ou status ont une valeur invalide
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        if (isset($query['status']) && !in_array($query['status'], self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('"status" must be one of: %s. Got: "%s"', implode(', ', self::STATUSES), (string) $query['status'])
            );
        }

        return parent::list($query);
    }
}
