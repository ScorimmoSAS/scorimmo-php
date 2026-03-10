# scorimmo-php

SDK officiel PHP pour la plateforme CRM immobilier [Scorimmo](https://pro.scorimmo.com).

Facilite l'intégration des leads Scorimmo dans votre CRM en deux modes :
- **Client API** — récupérez vos leads avec gestion automatique du token JWT
- **Réception de webhooks** — recevez et traitez les événements Scorimmo en temps réel

---

## Installation

```bash
composer require scorimmo/scorimmo-php
```

**Prérequis :** PHP ≥ 8.1, ext-curl, ext-json

---

## Client API

```php
use Scorimmo\Client\ScorimmoClient;

$client = new ScorimmoClient(
    username: 'votre-identifiant-api',
    password: 'votre-mot-de-passe-api',
    // baseUrl: 'https://pro.scorimmo.com' (par défaut)
);

// Récupérer tous les leads des dernières 24h (pagination automatique)
$leads = $client->leads->since(new DateTime('-24 hours'));

// Récupérer un lead par son ID
$lead = $client->leads->get(42);

// Rechercher des leads
$result = $client->leads->list([
    'search'  => ['external_lead_id' => 'MON-CRM-001'],
    'order'   => 'desc',
    'limit'   => 20,
]);

// Leads d'un point de vente spécifique
$storeLeads = $client->leads->listByStore(1, ['limit' => 50]);
```

---

## Réception de webhooks

### 1. Exposer une route dans votre application

```php
use Scorimmo\Webhook\ScorimmoWebhook;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

$webhook = new ScorimmoWebhook(
    headerValue: $_ENV['SCORIMMO_WEBHOOK_SECRET'],
    headerKey: 'X-Scorimmo-Key',
);

$headers = getallheaders();
$rawBody = file_get_contents('php://input');

try {
    $webhook->handle($headers, $rawBody, [
        'new_lead'     => function (array $lead): void {
            // Nouveau lead → créer dans votre CRM
        },
        'update_lead'  => function (array $e): void {
            // Lead modifié → mettre à jour
        },
        'new_comment'  => function (array $e): void {
            // Nouveau commentaire
        },
        'new_rdv'      => function (array $e): void {
            // Rendez-vous planifié
        },
        'new_reminder' => function (array $e): void {
            // Rappel planifié
        },
        'closure_lead' => function (array $e): void {
            // Lead clôturé → archiver
        },
    ]);

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (WebhookAuthException $e) {
    http_response_code(401);
} catch (WebhookValidationException $e) {
    http_response_code(400);
}
```

### 2. Transmettre l'URL à Scorimmo

Une fois votre route déployée (ex. `https://votre-crm.com/webhook/scorimmo`), communiquez les informations suivantes à votre **account manager Scorimmo** ou par e-mail à **assistance@scorimmo.com** :

```
URL du webhook : https://votre-crm.com/webhook/scorimmo
En-tête d'authentification :
  Clé   : X-Scorimmo-Key
  Valeur : votre-secret

Événements à activer :
  ☑ Nouveau lead        (new_lead)
  ☑ Mise à jour lead    (update_lead)
  ☑ Nouveau commentaire (new_comment)
  ☑ Rendez-vous         (new_rdv)
  ☑ Rappel              (new_reminder)
  ☑ Clôture lead        (closure_lead)

Point(s) de vente concerné(s) : [indiquez vos points de vente]
```

---

## Événements webhook

| Événement | Déclencheur | Champs principaux |
|-----------|-------------|-------------------|
| `new_lead` | Nouveau lead créé | Objet lead complet (client, biens, vendeur...) |
| `update_lead` | Lead modifié | `id`, champs modifiés uniquement |
| `new_comment` | Commentaire ajouté | `lead_id`, `comment`, `created_at` |
| `new_rdv` | Rendez-vous créé | `lead_id`, `start_time`, `location`, `detail` |
| `new_reminder` | Rappel créé | `lead_id`, `start_time`, `detail` |
| `closure_lead` | Lead clôturé | `lead_id`, `status`, `close_reason` |

---

## Gestion des erreurs

```php
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;

try {
    $lead = $client->leads->get(999);
} catch (ScorimmoApiException $e) {
    echo $e->getStatusCode(); // ex: 404
    echo $e->getMessage();    // "Lead not found"
} catch (ScorimmoAuthException $e) {
    echo 'Vérifiez vos identifiants API';
}
```

---

## Support

- Account manager Scorimmo
- **assistance@scorimmo.com**
- [pro.scorimmo.com](https://pro.scorimmo.com)
