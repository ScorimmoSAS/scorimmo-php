<?php

namespace Scorimmo\Client;

/**
 * Ressource WebCallbacks — déclenche un appel sortant via l'API webcallback (POST /api/v2/webcallbacks).
 *
 * ATTENTION : cet endpoint n'utilise PAS l'authentification Bearer classique. Il s'authentifie
 * avec une clé personnelle « WebCallback » (paramètre `key` du body). Cette clé est distincte
 * des credentials email/password de l'API et est configurée par point de vente.
 *
 * Endpoints couverts :
 *  POST  /api/v2/webcallbacks   → launch()
 *
 * @scope aucun (clé WCB en body)
 */
class WebCallbacksResource extends AbstractResource
{
    protected function basePath(): string
    {
        return '/api/v2/webcallbacks';
    }

    /**
     * Déclenche un appel sortant vers un numéro depuis le point de vente rattaché à la clé WCB.
     *
     * @param  string $key           Clé personnelle WebCallback (fournie par Scorimmo, distincte du couple email/password)
     * @param  string $numberToCall  Numéro de téléphone destinataire au format international ou local
     * @return array{results: array<int, string>, information: int}
     * @throws \InvalidArgumentException Si l'un des deux paramètres est vide
     *
     * @scope aucun (clé WCB en body)
     */
    public function launch(string $key, string $numberToCall): array
    {
        if ($key === '' || $numberToCall === '') {
            throw new \InvalidArgumentException('launch() requires both "key" and "numberToCall" to be non-empty');
        }

        // Bypass de l'authentification Bearer : on passe par requestUnauthenticated() —
        // le body porte lui-même la clé d'authentification (paramètre `key`).
        return $this->client->requestUnauthenticated('POST', $this->basePath(), [
            'key'            => $key,
            'number_to_call' => $numberToCall,
        ]);
    }
}
