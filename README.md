# scorimmo-php

SDK officiel PHP pour la plateforme CRM immobilier [Scorimmo](https://pro.scorimmo.com).

Inclut les intégrations natives **Laravel** et **Symfony**.

> **Documentation de référence :**
> [API REST](https://pro.scorimmo.com/api/doc) · [Webhooks](https://pro.scorimmo.com/webhook/doc)

---

## Sommaire

- [Installation](#installation)
- [Identifiants API](#identifiants-api)
- [Client API — PHP natif](#client-api--php-natif)
- [Webhooks — PHP natif](#webhooks--php-natif)
- [Intégration Laravel](#intégration-laravel)
- [Intégration Symfony](#intégration-symfony)
- [Référence — Méthodes leads](#référence--méthodes-leads)
- [Référence — Événements webhook](#référence--événements-webhook)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Support](#support)

---

## Installation

```bash
composer require scorimmo/scorimmo-php
```

**Prérequis :** PHP ≥ 8.1, extensions `curl` et `json` (activées par défaut sur la plupart des hébergements).

---

## Identifiants API

Les identifiants (`username` / `password`) sont les mêmes que ceux utilisés pour se connecter à [pro.scorimmo.com](https://pro.scorimmo.com).

Pour le webhook, le secret (`SCORIMMO_WEBHOOK_SECRET`) est une valeur que vous choisissez librement — communiquez-la ensuite à Scorimmo lors de la configuration (voir [Configurer le webhook chez Scorimmo](#configurer-le-webhook-chez-scorimmo)).

---

## Client API — PHP natif

### Initialisation

```php
use Scorimmo\Client\ScorimmoClient;

$client = new ScorimmoClient(
    username: 'votre-identifiant',
    password: 'votre-mot-de-passe',
);
```

Le token JWT est géré automatiquement : récupéré à la première requête, mis en cache en mémoire, renouvelé à l'expiration, et rafraîchi automatiquement en cas de réponse 401.

### Récupérer les leads récents

```php
// Tous les leads des dernières 24 heures (pagination automatique)
$leads = $client->leads->since(new DateTime('-24 hours'));

// Depuis une date précise
$leads = $client->leads->since('2024-06-01 00:00:00');

// Leads modifiés récemment (plutôt que créés)
$leads = $client->leads->since(new DateTime('-1 hour'), field: 'updated_at');

// Scopé à un point de vente (recommandé si le token est lié à une agence)
$leads = $client->leads->since(new DateTime('-24 hours'), storeId: 776);
```

### Récupérer un lead par ID

```php
$lead = $client->leads->get(42);
```

### Rechercher des leads

```php
// Par ID externe (votre référence CRM)
$result = $client->leads->list([
    'search' => ['external_lead_id' => 'MON-CRM-001'],
]);

// Par email client
$result = $client->leads->list([
    'search' => ['email' => 'client@exemple.com'],
]);

// Avec tri et pagination
$result = $client->leads->list([
    'search'  => ['status' => 'new'],
    'orderby' => 'created_at',
    'order'   => 'desc',
    'limit'   => 20,
    'page'    => 1,
]);

// $result['results'] contient les leads, $result['total'] le nombre total
foreach ($result['results'] as $lead) {
    echo $lead['id'] . ' — ' . $lead['customer']['first_name'] . PHP_EOL;
}
```

### Leads par point de vente

```php
$result = $client->leads->listByStore(storeId: 5, query: [
    'orderby' => 'created_at',
    'order'   => 'desc',
    'limit'   => 50,
]);
```

---

## Webhooks — PHP natif

Les webhooks permettent à Scorimmo de notifier votre application en temps réel lors d'événements (nouveau lead, mise à jour, etc.).

### Initialisation

```php
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    headerValue: 'votre-secret-webhook',
    headerKey: 'X-Scorimmo-Key', // valeur par défaut, modifiable
);
```

### Traitement d'une requête entrante

```php
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

// Récupération des headers et du corps de la requête
$headers = getallheaders();
$rawBody = file_get_contents('php://input');

try {
    $webhook->handle($headers, $rawBody, [

        'new_lead' => function (array $event): void {
            // $event contient l'objet lead complet
            $lead = $event; // id, store_id, customer, interest, origin, etc.
            // Insérer dans votre base de données...
        },

        'update_lead' => function (array $event): void {
            // $event['id'] = ID du lead, + champs modifiés uniquement
        },

        'new_comment' => function (array $event): void {
            // $event['lead_id'], $event['comment']
        },

        'new_rdv' => function (array $event): void {
            // $event['lead_id'], $event['start_time'], $event['location']
        },

        'new_reminder' => function (array $event): void {
            // $event['lead_id'], $event['start_time']
        },

        'closure_lead' => function (array $event): void {
            // $event['lead_id'], $event['status'], $event['close_reason']
        },

        // Événement non reconnu (optionnel)
        'unknown' => function (array $event): void {
            error_log('Événement Scorimmo inconnu : ' . $event['event']);
        },

    ]);

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (WebhookAuthException $e) {
    // Header d'authentification absent ou incorrect
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);

} catch (WebhookValidationException $e) {
    // Payload JSON invalide ou champ "event" manquant
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request']);
}
```

> **Important :** Scorimmo considère la livraison réussie uniquement si votre endpoint retourne HTTP 200. Tout autre code est ignoré.

### Configurer le webhook chez Scorimmo

Une fois votre endpoint déployé, transmettez les informations suivantes à votre **account manager Scorimmo** (voir [Support](#support)) :

```
URL du webhook : https://votre-app.com/webhook/scorimmo
En-tête d'authentification :
  Clé   : X-Scorimmo-Key
  Valeur : [votre SCORIMMO_WEBHOOK_SECRET]

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

## Intégration Laravel

### Installation

```bash
php artisan vendor:publish --tag=scorimmo-config
```

Dans votre `.env` :

```env
SCORIMMO_USERNAME=votre-identifiant
SCORIMMO_PASSWORD=votre-mot-de-passe
SCORIMMO_WEBHOOK_SECRET=votre-secret-webhook
```

Le service provider est enregistré automatiquement via la découverte de packages Laravel.

### Utiliser le client

```php
use Scorimmo\Client\ScorimmoClient;

class LeadController extends Controller
{
    public function __construct(private ScorimmoClient $scorimmo) {}

    public function index()
    {
        $leads = $this->scorimmo->leads->since(now()->subDay());
        return response()->json($leads);
    }
}
```

### Réception de webhooks

La route `POST /webhook/scorimmo` est enregistrée automatiquement.

**Étape 1 — Exclure la route du CSRF** dans `bootstrap/app.php` :

```php
$middleware->validateCsrfTokens(except: [
    config('scorimmo.webhook_path'),
]);
```

**Étape 2 — Écouter les événements** dans `AppServiceProvider` ou un `EventServiceProvider` :

```php
use Illuminate\Support\Facades\Event;

// Dans la méthode boot()
Event::listen('scorimmo.new_lead', function (array $lead): void {
    // $lead contient l'objet lead complet
    // Créer un contact, envoyer un email, etc.
});

Event::listen('scorimmo.update_lead', function (array $event): void {
    // $event['id'] + champs modifiés
});

Event::listen('scorimmo.closure_lead', function (array $event): void {
    // $event['lead_id'], $event['status'], $event['close_reason']
});

Event::listen('scorimmo.new_comment',  fn(array $e) => /* ... */);
Event::listen('scorimmo.new_rdv',      fn(array $e) => /* ... */);
Event::listen('scorimmo.new_reminder', fn(array $e) => /* ... */);
```

> Les erreurs d'authentification et de validation sont gérées automatiquement par le contrôleur intégré (retourne 401 ou 400 selon le cas).

---

## Intégration Symfony

### Configuration

**Étape 1 — Enregistrer le bundle** dans `config/bundles.php` :

```php
return [
    // ...
    Scorimmo\Bridge\Symfony\ScorimmoBundle::class => ['all' => true],
];
```

**Étape 2 — Créer** `config/packages/scorimmo.yaml` :

```yaml
scorimmo:
    username:       '%env(SCORIMMO_USERNAME)%'
    password:       '%env(SCORIMMO_PASSWORD)%'
    webhook_secret: '%env(SCORIMMO_WEBHOOK_SECRET)%'
```

**Étape 3 — Ajouter dans** `.env` :

```env
SCORIMMO_USERNAME=votre-identifiant
SCORIMMO_PASSWORD=votre-mot-de-passe
SCORIMMO_WEBHOOK_SECRET=votre-secret-webhook
```

### Utiliser le client

```php
use Scorimmo\Client\ScorimmoClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class LeadController extends AbstractController
{
    public function __construct(private ScorimmoClient $scorimmo) {}

    #[Route('/leads', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $leads = $this->scorimmo->leads->since(new \DateTime('-24 hours'));
        return $this->json($leads);
    }
}
```

### Réception de webhooks

Créez un contrôleur dédié pour recevoir les webhooks :

```php
use Scorimmo\Webhook\ScorimmoWebhook;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class WebhookController extends AbstractController
{
    public function __construct(private ScorimmoWebhook $webhook) {}

    #[Route('/webhook/scorimmo', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // headers->all() retourne array<string, string[]> ; on aplatit en array<string, string>
        $headers = array_map(fn(array $v) => $v[0] ?? '', $request->headers->all());

        try {
            $event = $this->webhook->parse(
                $headers,
                $request->getContent()
            );
        } catch (WebhookAuthException) {
            return $this->json(['error' => 'Unauthorized'], 401);
        } catch (WebhookValidationException) {
            return $this->json(['error' => 'Bad Request'], 400);
        }

        match ($event['event']) {
            'new_lead'     => $this->onNewLead($event),
            'update_lead'  => $this->onUpdateLead($event),
            'closure_lead' => $this->onClosureLead($event),
            default        => null,
        };

        return $this->json(['ok' => true]);
    }

    private function onNewLead(array $event): void
    {
        // Créer le contact dans votre base de données...
    }

    private function onUpdateLead(array $event): void
    {
        // Mettre à jour le contact...
    }

    private function onClosureLead(array $event): void
    {
        // Archiver le contact...
    }
}
```

---

## Référence — Méthodes leads

### `leads->get(int $id): array`

Retourne un lead complet par son ID Scorimmo.

### `leads->since(string|\DateTimeInterface $date, string $field = 'created_at', int $maxPages = 100, ?int $storeId = null): array`

Retourne tous les leads créés (ou modifiés) après `$date`. La pagination est gérée automatiquement — le résultat est un tableau plat dédupliqué.

- `$field` : `'created_at'` (défaut) ou `'updated_at'`
- `$maxPages` : plafond de pages récupérées (défaut : 100, soit ~5 000 leads avec `limit=50`)
- `$storeId` : restreint à un point de vente (`/api/stores/{id}/leads`) ; `null` = global

```php
// Scopé à un point de vente (recommandé si le token est lié à une agence)
$leads = $client->leads->since(new DateTime('-24 hours'), storeId: 776);
```

### `leads->list(array $query = []): array`

Retourne une page de leads. Le tableau `$query` accepte :

| Paramètre | Type | Description |
|---|---|---|
| `search` | `array` | Filtres par champ (voir ci-dessous) |
| `orderby` | `string` | Champ de tri : `created_at`, `updated_at`, `status`, etc. |
| `order` | `string` | `'asc'` ou `'desc'` |
| `limit` | `int` | Nombre de résultats par page (défaut : 20) |
| `page` | `int` | Numéro de page (défaut : 1) |

**Filtres `search` disponibles :**

| Clé | Exemple |
|---|---|
| `id` | `['id' => '42']` |
| `created_at` | `['created_at' => '>2024-01-01 00:00:00']` |
| `updated_at` | `['updated_at' => '>2024-06-01 00:00:00']` |
| `closed_date` | `['closed_date' => '<2024-12-31 00:00:00']` |
| `anonymized_at` | `['anonymized_at' => '>2024-01-01 00:00:00']` |
| `status` | `['status' => 'Affecté']` |
| `interest` | `['interest' => 'TRANSACTION']` |
| `origin` | `['origin' => 'TRANSFERT AGENCE']` |
| `customer_firstname` | `['customer_firstname' => 'Jean']` |
| `customer_lastname` | `['customer_lastname' => 'Dupont']` |
| `email` | `['email' => 'client@exemple.com']` |
| `phone` | `['phone' => '0600000000']` |
| `other_phone_number` | `['other_phone_number' => '0600000000']` |
| `seller_id` | `['seller_id' => '3533']` |
| `seller_firstname` | `['seller_firstname' => 'Sofiane']` |
| `seller_lastname` | `['seller_lastname' => 'Dupont']` |
| `reference` | `['reference' => 'REF-001']` |
| `external_lead_id` | `['external_lead_id' => 'MON-CRM-001']` |
| `external_customer_id` | `['external_customer_id' => 'CLIENT-456']` |
| `seller_present_on_creation` | `['seller_present_on_creation' => '1']` |
| `transfered` | `['transfered' => '0']` |

Opérateurs de comparaison sur les dates et ids : `>`, `>=`, `<`, `<=` (préfixe la valeur). Sans opérateur, la comparaison est une égalité stricte. Plusieurs filtres peuvent être combinés dans le même tableau.

```php
// Combinaison de filtres
$result = $client->leads->list([
    'search' => [
        'seller_id'  => '3533',
        'status'     => 'Affecté',
        'created_at' => '>2026-03-01 00:00:00',
    ],
    'orderby' => 'created_at',
    'order'   => 'desc',
    'limit'   => 20,
]);
```

Retourne `['results' => [...], 'informations' => [...]]`.

### `leads->listByStore(int $storeId, array $query = []): array`

Identique à `list()` mais limité à un point de vente spécifique. Mêmes paramètres `$query`.

---

## Référence — Événements webhook

| Événement | Déclencheur | Champs principaux du payload |
|---|---|---|
| `new_lead` | Nouveau lead créé | Objet lead complet (`id`, `store_id`, `customer`, `interest`, `origin`, `seller`, `status`, `created_at`, …) |
| `update_lead` | Lead modifié | `id`, `updated_at`, champs modifiés uniquement |
| `new_comment` | Commentaire ajouté | `lead_id`, `comment`, `created_at` |
| `new_rdv` | Rendez-vous créé | `lead_id`, `start_time`, `location`, `type` |
| `new_reminder` | Rappel créé | `lead_id`, `start_time`, `type` (`offer` ou `recontact`) |
| `closure_lead` | Lead clôturé | `lead_id`, `status` (`SUCCESS`, `CLOSED`, `CLOSE_OPERATOR`), `close_reason` |

> Pour la structure complète de chaque payload, consultez la [documentation webhooks](https://pro.scorimmo.com/webhook/doc).

---

## Gestion des erreurs

```php
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

// Erreurs API
try {
    $lead = $client->leads->get(999);
} catch (ScorimmoAuthException $e) {
    // Identifiants incorrects ou token expiré
    echo 'Erreur d\'authentification : ' . $e->getMessage();
} catch (ScorimmoApiException $e) {
    echo 'Erreur API ' . $e->statusCode . ' : ' . $e->getMessage();
    // Codes courants : 400 (requête invalide), 403 (accès refusé), 404 (lead inexistant)
}

// Erreurs webhook
try {
    $event = $webhook->parse($headers, $rawBody);
} catch (WebhookAuthException $e) {
    // Header absent ou valeur incorrecte → retourner HTTP 401
} catch (WebhookValidationException $e) {
    // JSON invalide ou champ "event" manquant → retourner HTTP 400
}
```

---

## Support

- Votre account manager Scorimmo
- [Formulaire de contact](https://pro.scorimmo.com/contact)
- [pro.scorimmo.com](https://pro.scorimmo.com)