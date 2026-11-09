<?php

namespace Scorimmo\Client;

/**
 * Ressource Webhooks — abonnements aux webhooks sortants des points de vente.
 *
 * Endpoints couverts :
 *  GET     /api/v2/webhooks          → list()
 *  GET     /api/v2/webhooks/{id}     → get()
 *  GET     /api/v2/webhooks/errors   → errors()
 *  POST    /api/v2/webhooks          → create()
 *  PATCH   /api/v2/webhooks/{id}     → update()
 *  DELETE  /api/v2/webhooks/{id}     → delete()
 *
 * Codes d'évènement (`events`) : CREATION_LEAD, UPDATE_LEAD, NEW_COMMENT, RDV, REMINDER,
 * CLOSE_LEAD, CAMPAIGN_CONTACT_UPDATE, CAMPAIGN_CONTACT_CLOSED, CAMPAIGN_CLOSED.
 * Les codes CAMPAIGN_* n'existent qu'en version 2 : l'API répond 400 sur un webhook v1.
 * Ils ne sont pas contrôlés par le SDK : Scorimmo peut en ajouter sans breaking change.
 *
 * Pour réception et vérification des webhooks, voir {@see \Scorimmo\Webhook\ScorimmoWebhook}.
 *
 * @scope ROLE_API_WEBHOOK_READ pour get() / list() / errors()
 * @scope ROLE_API_WEBHOOK_WRITE pour create() / update() / delete()
 */
class WebhooksResource extends AbstractResource
{
    /** @var string[] Champs de tri acceptés */
    private const SORT_FIELDS = ['id', 'created_at', 'updated_at', 'name'];

    protected function basePath(): string
    {
        return '/api/v2/webhooks';
    }

    /**
     * Liste les webhooks avec filtrage optionnel.
     *
     * @param array{
     *   page?:              int,
     *   limit?:             int,
     *   sort?:              string,
     *   ids?:               string,
     *   store_id?:          int,
     *   name?:              string,
     *   version?:           '1'|'2',
     *   event?:             string,
     *   'created_at[gte]'?: string,
     *   'created_at[lte]'?: string,
     * } $query
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si sort, limit ou page ont une valeur invalide
     *
     * @scope ROLE_API_WEBHOOK_READ
     */
    public function list(array $query = []): array
    {
        if (isset($query['sort'])) {
            $this->assertValidSort((string) $query['sort'], self::SORT_FIELDS);
        }

        return parent::list($query);
    }

    /**
     * Historique des échecs de livraison des webhooks du compte.
     *
     * @param array{
     *   page?:              int,
     *   limit?:             int,
     *   store_id?:          int,
     *   webhook_name?:      string,
     *   code?:              string,
     *   status?:            int,
     *   'created_at[gte]'?: string,
     *   'created_at[lte]'?: string,
     * } $query  `status` est le code HTTP retourné par l'URL cible.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     * @throws \InvalidArgumentException Si limit ou page ont une valeur invalide
     *
     * @scope ROLE_API_WEBHOOK_READ
     */
    public function errors(array $query = []): array
    {
        $this->assertValidPagination($query);
        $qs = $this->buildQueryString($query);
        return $this->client->request('GET', $this->basePath() . '/errors' . ($qs ? "?{$qs}" : ''));
    }

    /**
     * Crée un webhook sur un point de vente. La version du webhook (champ `version` de la
     * réponse) est fixée par Scorimmo.
     *
     * @param array{
     *   store_id:   int,
     *   name:       string,
     *   url:        string,
     *   events:     string[],
     *   headers?:   array<int, array{key: string, value: string}>|null,
     *   interests?: string[]|null,
     * } $data  `interests` vide ou omis = tous les univers.
     *
     * @return array<string, mixed>        Webhook créé
     * @throws \InvalidArgumentException   Si un champ requis est manquant
     *
     * @scope ROLE_API_WEBHOOK_WRITE
     */
    public function create(array $data): array
    {
        foreach (['store_id', 'name', 'url', 'events'] as $required) {
            if (!isset($data[$required])) {
                throw new \InvalidArgumentException(
                    sprintf('create() requires "%s" in payload', $required)
                );
            }
        }

        return $this->client->request('POST', $this->basePath(), $data);
    }

    /**
     * Mise à jour partielle d'un webhook (seuls les champs transmis sont modifiés).
     *
     * Champs modifiables : name, url, events, headers, interests.
     *
     * @param  array<string, mixed> $data  Champs à modifier
     * @return array<string, mixed>        Webhook mis à jour
     * @throws \InvalidArgumentException   Si $data est vide
     *
     * @scope ROLE_API_WEBHOOK_WRITE
     */
    public function update(int $id, array $data): array
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('update() requires at least one field to modify');
        }

        return $this->client->request('PATCH', $this->basePath() . "/{$id}", $data);
    }

    /**
     * Supprime un webhook.
     *
     * @scope ROLE_API_WEBHOOK_WRITE
     */
    public function delete(int $id): void
    {
        $this->client->request('DELETE', $this->basePath() . "/{$id}");
    }
}
