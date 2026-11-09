<?php

namespace Scorimmo\Client;

use Scorimmo\Exception\ScorimmoApiException;

/**
 * Ressource CampaignContacts — contacts des campagnes d'appels.
 *
 * Endpoints couverts :
 *  GET   /api/v2/campaigns/contacts        → list()
 *  GET   /api/v2/campaigns/contacts/{id}   → get()
 *  POST  /api/v2/campaigns/contacts        → inject()
 *
 * @scope ROLE_API_CAMPAIGN_READ (campaign:read) pour get() / list()
 * @scope ROLE_API_CAMPAIGN_WRITE (campaign:write) pour inject()
 */
class CampaignContactsResource extends AbstractResource
{
    /** Nombre maximum de contacts acceptés par inject(). */
    public const MAX_CONTACTS = 100;

    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id', 'created_at', 'updated_at', 'last_contact_at'];

    /** @var string[] Statuts acceptés */
    private const STATUSES = ['new', 'recontact', 'no_answer', 'closed', 'validated'];

    /** @var string[] Sous-statuts acceptés */
    private const SUB_STATUSES = ['no_follow', 'refuse_answer', 'delete_contact', 'no_answer'];

    protected function basePath(): string
    {
        return '/api/v2/campaigns/contacts';
    }

    /**
     * Liste les contacts d'une campagne.
     *
     * @param array{
     *   campaign_id:            int,
     *   page?:                  int,
     *   limit?:                 int,
     *   sort?:                  string,
     *   ids?:                   string,
     *   status?:                'new'|'recontact'|'no_answer'|'closed'|'validated',
     *   sub_status?:            'no_follow'|'refuse_answer'|'delete_contact'|'no_answer',
     *   'created_at[gte]'?:     string,
     *   'updated_at[gte]'?:     string,
     *   'last_contact_at[gte]'?: string,
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si campaign_id est absent, ou si sort, status ou sub_status ont une valeur invalide
     *
     * @scope ROLE_API_CAMPAIGN_READ (campaign:read)
     */
    public function list(array $query = []): array
    {
        if (!isset($query['campaign_id'])) {
            throw new \InvalidArgumentException('list() requires "campaign_id" in query');
        }

        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        if (isset($query['status']) && !in_array($query['status'], self::STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('"status" must be one of: %s. Got: "%s"', implode(', ', self::STATUSES), (string) $query['status'])
            );
        }

        if (isset($query['sub_status']) && !in_array($query['sub_status'], self::SUB_STATUSES, true)) {
            throw new \InvalidArgumentException(
                sprintf('"sub_status" must be one of: %s. Got: "%s"', implode(', ', self::SUB_STATUSES), (string) $query['sub_status'])
            );
        }

        return parent::list($query);
    }

    /**
     * Injecte jusqu'à 100 contacts dans une campagne.
     *
     * Chaque contact est validé séparément : un contact refusé n'empêche pas l'enregistrement
     * des autres. Le compte rendu est retourné même si tous les contacts sont refusés (l'API
     * répond alors 422 avec le même corps) ; consulter `meta.rejected` et `data[].errors`.
     *
     * @param array<int, array{
     *   last_name:    string,
     *   phone:        string,
     *   first_name?:  string|null,
     *   email?:       string|null,
     *   title?:       string|null,
     *   zip_code?:    string|null,
     *   city?:        string|null,
     *   legal_name?:  string|null,
     *   store_label?: string|null,
     *   comment?:     string|null,
     *   answers?:     array<string, mixed>,
     * }> $contacts  `answers` est indexé par les `label` de CampaignFieldsResource.
     *
     * @return array{
     *   data: array<int, array{index: int, status: 'created'|'rejected', id?: int, errors?: array<int, array{field: string, message: string}>}>,
     *   meta: array{received: int, created: int, rejected: int},
     * }
     * @throws \InvalidArgumentException Si $contacts est vide ou dépasse 100 éléments
     * @throws ScorimmoApiException      404 campagne introuvable, 409 injection déjà en cours (réessayer),
     *                                   422 campagne clôturée ou corps invalide
     *
     * @scope ROLE_API_CAMPAIGN_WRITE (campaign:write)
     */
    public function inject(int $campaignId, array $contacts): array
    {
        if ($contacts === [] || count($contacts) > self::MAX_CONTACTS) {
            throw new \InvalidArgumentException(
                sprintf('inject() requires between 1 and %d contacts, got: %d', self::MAX_CONTACTS, count($contacts))
            );
        }

        try {
            return $this->client->request('POST', $this->basePath(), [
                'campaign_id' => $campaignId,
                'contacts'    => array_values($contacts),
            ]);
        } catch (ScorimmoApiException $e) {
            if ($e->statusCode === 422 && isset($e->body['data'], $e->body['meta'])) {
                return $e->body;
            }

            throw $e;
        }
    }
}
