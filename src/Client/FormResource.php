<?php

namespace Scorimmo\Client;

/**
 * Ressource Form — soumission de formulaires publics (POST /api/v2/form).
 *
 * Cet endpoint est destiné à recevoir des soumissions de formulaires depuis vos sites web
 * (landing pages, formulaires de contact, portails partenaires). Il crée un lead dans le CRM
 * après notification email au(x) destinataire(s) indiqué(s).
 *
 * Scope requis : ROLE_API_FORM_WRITE (à demander séparément de lead:write).
 *
 * Endpoints couverts :
 *  POST  /api/v2/form   → submit()
 *
 * Le référentiel des champs autorisés (requests, additional_fields) est disponible via
 * RequestFieldsResource et AdditionalFieldsResource.
 */
class FormResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/form';
    }

    /**
     * Soumet un formulaire public. Crée un lead et envoie l'email au(x) destinataire(s).
     *
     * @param array{
     *   store_id:          int,
     *   libelle_id:        int,
     *   to_email:          string|string[],
     *   origin:            string,
     *   message:           string,
     *   subject?:          string,
     *   customer?:         array{
     *     civility?:   'M.'|'Mme',
     *     first_name?: string,
     *     last_name?:  string,
     *     email?:      string,
     *     phone?:      string,
     *   },
     *   requests?:         array<int, array<string, mixed>>,
     *   additional_fields?: array<int, array<string, mixed>>,
     *   external_lead_id?: string,
     * } $data
     *
     * @return array{status: int, message: string, id: int, store_id: int, libelle_id: int, origin: string}
     * @throws \InvalidArgumentException Si un champ requis est manquant
     */
    public function submit(array $data): array
    {
        foreach (['store_id', 'libelle_id', 'to_email', 'origin', 'message'] as $required) {
            if (!isset($data[$required])) {
                throw new \InvalidArgumentException(
                    sprintf('submit() requires "%s" in payload', $required)
                );
            }
        }

        return $this->client->request('POST', $this->basePath(), $data);
    }
}
