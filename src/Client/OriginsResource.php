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
 * Scope requis : ref:read.
 */
class OriginsResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id'];

    /** @var string[] Canaux de tracking acceptés */
    private const TRACKING_CHANNELS = ['phone', 'email'];

    protected function basePath(): string
    {
        return '/api/v2/origins';
    }

    /**
     * Liste les origines avec filtrage optionnel.
     *
     * @param array{
     *   page?:              int,
     *   limit?:             int,
     *   sort?:              string,
     *   store_id?:          int,
     *   has_tracking?:      bool,
     *   tracking_channel?:  'phone'|'email',
     *   include?:           string,
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort ou tracking_channel ont une valeur invalide
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        if (isset($query['tracking_channel']) && !in_array($query['tracking_channel'], self::TRACKING_CHANNELS, true)) {
            throw new \InvalidArgumentException(
                sprintf('"tracking_channel" must be one of: %s. Got: "%s"',
                    implode(', ', self::TRACKING_CHANNELS),
                    (string) $query['tracking_channel']
                )
            );
        }

        if (isset($query['has_tracking']) && is_bool($query['has_tracking'])) {
            $query['has_tracking'] = $query['has_tracking'] ? 'true' : 'false';
        }

        return parent::list($query);
    }
}
