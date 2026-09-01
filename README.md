# scorimmo-php

SDK officiel PHP pour la plateforme [Scorimmo](https://pro.scorimmo.com).

Inclut les intégrations natives **Laravel** et **Symfony**.

> **Documentation de référence :**
> [API REST](https://pro.scorimmo.com/api/v2/doc) · [Webhooks](https://pro.scorimmo.com/webhook/doc)

---

## Sommaire

- [Installation](#installation)
- [Identifiants API](#identifiants-api)
- [Client API - PHP natif](#client-api--php-natif)
- [Webhooks - PHP natif](#webhooks--php-natif)
- [Intégration Laravel](#intégration-laravel)
- [Intégration Symfony](#intégration-symfony)
- [Référence - Méthodes leads](#référence--méthodes-leads)
- [Référence - Ressources secondaires](#référence--ressources-secondaires)
- [Référence - Gestion des tokens](#référence--gestion-des-tokens)
- [Référence - Événements webhook](#référence--événements-webhook)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Support](#support)

---

## Installation

```bash
composer require scorimmo/scorimmo-php
```

**Prérequis :** PHP ≥ 8.1, extension `json` (activée par défaut). La dépendance HTTP ([Guzzle](https://github.com/guzzle/guzzle)) est installée automatiquement par Composer.

---

## Identifiants API

Les identifiants (`email` / `password`) sont ceux fournis par Scorimmo. L'identifiant est l'adresse email du compte API.

Après le premier appel authentifié, un **refresh token** est disponible via `getRefreshToken()`. Il permet de réinitialiser le client sans repasser par les identifiants (voir [Gestion des tokens](#référence--gestion-des-tokens)).

Pour le webhook, le secret HMAC (`SCORIMMO_WEBHOOK_SIGNATURE_SECRET`) est une valeur que vous choisissez librement - communiquez-la ensuite à Scorimmo lors de la configuration (voir [Configurer le webhook chez Scorimmo](#configurer-le-webhook-chez-scorimmo)).

---

## Client API — PHP natif

### Initialisation

**Avec identifiants email/password :**

```php
use Scorimmo\Client\ScorimmoClient;

$client = new ScorimmoClient(
    email:    'api@votre-agence.fr',
    password: 'votre-mot-de-passe',
);
```

**Avec un refresh token** (sans exposer les identifiants) :

```php
// Récupérer le refresh token après le premier appel authentifié et le persister
$client->getToken(); // déclenche l'auth initiale
$refreshToken = $client->getRefreshToken();
// → stocker $refreshToken (cache, session, coffre-fort de secrets…)

// Au démarrage suivant, passer directement le refresh token :
$client = new ScorimmoClient(refreshToken: $refreshToken);
```

Le token JWT est géré automatiquement : récupéré à la première requête, mis en cache en mémoire, et renouvelé via le refresh token à l'expiration. Si le refresh token est rejeté et que des identifiants sont disponibles, le client bascule automatiquement sur l'authentification email/password.

### Activer les logs (optionnel)

Le client accepte un logger [PSR-3](https://www.php-fig.org/psr/psr-3/) (Monolog, Laravel Log, etc.). Sans logger, tout est silencieux.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('scorimmo');
$logger->pushHandler(new StreamHandler('php://stdout'));

$client = new ScorimmoClient(
    email:    'api@votre-agence.fr',
    password: 'votre-mot-de-passe',
    logger:   $logger,
);
```

**Niveaux de log émis :**

| Niveau | Événement |
|---|---|
| `DEBUG` | Chaque requête HTTP envoyée (`→ GET /api/v2/leads`) et réponse reçue (`← 200 … 42ms`) |
| `INFO` | Obtention ou renouvellement d'un token JWT |
| `WARNING` | Refresh token rejeté, bascule sur email/password |
| `ERROR` | Erreur d'authentification, erreur API (4xx/5xx), échec réseau |

### Récupérer les leads récents

```php
// Tous les leads des dernières 24 heures (pagination automatique)
$leads = $client->leads->since(new DateTime('-24 hours'));

// Depuis une date précise — string : Y-m-d, Y-m-d H:i:s, ou ISO 8601
$leads = $client->leads->since('2024-06-01');
$leads = $client->leads->since('2024-06-01 08:00:00');
$leads = $client->leads->since('2024-06-01T08:00:00+02:00');

// Leads modifiés récemment (plutôt que créés)
$leads = $client->leads->since(new DateTime('-1 hour'), field: 'updated_at');

// Scopé à un point de vente
$leads = $client->leads->since(new DateTime('-24 hours'), storeId: 776);

// Avec chargement de relations (customer, seller, appointments, reminders, requests, comments)
$leads = $client->leads->since(new DateTime('-24 hours'), include: ['customer', 'seller']);
```

### Récupérer un lead par ID

```php
$lead = $client->leads->get(42);

// Avec relations embarquées
$lead = $client->leads->get(42, include: ['customer', 'appointments', 'comments']);
```

### Rechercher des leads

```php
// Par ID externe (votre référence CRM)
$result = $client->leads->list([
    'external_lead_id' => 'MON-CRM-001',
]);

// Par email client
$result = $client->leads->list([
    'customer.email' => 'client@exemple.com',
]);

// Par point de vente, avec tri et pagination
$result = $client->leads->list([
    'store_id' => 5,
    'sort'     => 'created_at:desc',
    'limit'    => 20,
    'page'     => 1,
]);

// Filtres de date avec opérateurs bracket
$result = $client->leads->list([
    'created_at[gte]' => '2024-01-01T00:00:00+00:00',
    'status'          => 'Affecté',
    'sort'            => 'created_at:asc',
]);

// $result['data'] contient les leads, $result['meta'] la pagination
foreach ($result['data'] as $lead) {
    echo $lead['id'] . ' - ' . $lead['customer']['first_name'] . PHP_EOL;
}
echo 'Total : ' . $result['meta']['total_items'] . PHP_EOL;
```

### Autres ressources disponibles

L'API v2 expose 12 ressources accessibles via le client.

**Données liées aux leads**

```php
// Rendez-vous d'un lead
$appointments = $client->appointments->list(['lead_id' => 42]);

// Commentaires d'un lead
$comments = $client->comments->list(['lead_id' => 42]);

// Rappels d'un lead
$reminders = $client->reminders->list(['lead_id' => 42]);

// Biens recherchés ou proposés sur un lead
$requests = $client->requests->list(['lead_id' => 42]);

// Contacts / prospects
$customer = $client->customers->get(123);
```

**Référentiels (valeurs disponibles pour les filtres et formulaires)**

```php
// Points de vente accessibles avec ce token
$stores = $client->stores->list();
$store  = $client->stores->get(5);

// Conseillers et managers
$users = $client->users->list(['store_id' => 5]);

// Statuts et sous-statuts disponibles
// → utiliser `label` comme valeur du filtre `status` dans leads->list()
$statuses = $client->status->list();

// Origines configurées sur le compte
// → utiliser `label` comme valeur du filtre `origin` dans leads->list()
$origins = $client->origins->list(['store_id' => 5]);

// Avec les traceurs associés (numéros ou emails de tracking)
$origins = $client->origins->list(['store_id' => 5, 'include' => 'tracking']);

// Champs additionnels configurés par agence / intérêt
// → utiliser les `label` comme clés dans `additional_fields` lors de la soumission d'un formulaire
$additionalFields = $client->additionalFields->list(['store_id' => 5, 'interest' => 'Location']);

// Champs de demande (critères de recherche) configurés par agence / intérêt
// → utiliser les `label` comme clés dans le tableau `requests` lors de la soumission d'un formulaire
$requestFields = $client->requestFields->list(['store_id' => 5, 'interest' => 'Location']);
```

Toutes les ressources exposent `list(array $query = [])` et `get(int $id)`.

> Pour les ressources `leads`, `appointments`, `comments`, `reminders` et `requests`, `get(int $id)` retourne la ressource complète par son ID Scorimmo. Pour `leads->get()`, passer `include: ['customer', ...]` pour charger les relations.

---

## Webhooks — PHP natif

Les webhooks permettent à Scorimmo de notifier votre application en temps réel lors d'événements (nouveau lead, mise à jour, etc.).

### Initialisation

L'authentification des webhooks est **optionnelle** — la sécurisation de l'endpoint est à la charge de l'intégrateur. Scorimmo propose plusieurs mécanismes complémentaires :

1. **Signature HMAC-SHA256** (fortement recommandé) — Scorimmo signe le corps brut avec un secret partagé et envoie la signature dans le header `X-Signature-256` sous la forme `sha256=<hex>`. Le SDK la vérifie en temps constant.
2. **HTTP Basic auth via URL** — enregistrez le webhook comme `https://user:pass@host/path`, l'auth est déléguée au serveur/framework, transparente pour le SDK.
3. **Restriction réseau** (IP whitelist, VPN, mTLS…) — hors périmètre du SDK.

**Avec signature HMAC (recommandé) :**

```php
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    signatureSecret: 'votre-secret-hmac',
    // signatureHeader: 'X-Signature-256', // valeur par défaut, personnalisable
);
```

**Sans vérification** (auth déportée sur le serveur) :

```php
$webhook = new ScorimmoWebhook();
```

> **Important :** si vous activez la signature, le corps brut (`php://input`) doit être lu **avant tout `json_decode()`**, sinon la signature ne pourra pas être vérifiée.

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

### Headers webhook v2

Chaque requête webhook envoyée par Scorimmo inclut ces headers :

| Header | Exemple | Description |
|---|---|---|
| `X-Signature-256` | `sha256=8f4c…` | Signature HMAC-SHA256 du corps brut (présent si vous avez configuré un secret ; nom personnalisable) |
| `X-Scorimmo-Event` | `lead.created` | Nom sémantique de l'événement |
| `X-Scorimmo-Version` | `2026-04-20` | Version d'API (date, format `YYYY-MM-DD` — pas un numéro sémantique) |
| `User-Agent` | `Scorimmo/1.42.0` | Version applicative Scorimmo (distincte de `X-Scorimmo-Version`) |

```php
$eventName  = $webhook->getSemanticEvent(getallheaders()); // ex: 'lead.created'
$apiVersion = $webhook->getApiVersion(getallheaders());    // ex: '2026-04-20'
```

Correspondance entre le champ `event` du payload et `X-Scorimmo-Event` :

| `event` (payload) | `X-Scorimmo-Event` |
|---|---|
| `new_lead` | `lead.created` |
| `update_lead` | `lead.updated` |
| `closure_lead` | `lead.closed` |
| `new_comment` | `lead.comment_added` |
| `new_rdv` | `lead.appointment_created` |
| `new_reminder` | `lead.reminder_created` |
| _(événement futur inconnu)_ | `webhook.<name>` |

> Enregistrez un handler `'unknown'` pour capturer les événements futurs non encore modélisés — Scorimmo peut ajouter de nouveaux événements sans breaking change.

### Idempotence & retries

Scorimmo effectue jusqu'à **6 tentatives de livraison** (initial + 5 retries) avec backoff exponentiel (5 s → 60 s max). Le corps et la signature sont identiques à chaque retry.

Aucun `Idempotency-Key` n'est envoyé — votre receveur doit être idempotent, typiquement en dédupliquant sur `(event, lead_id, created_at)` ou `(event, id, updated_at)` selon le type d'événement.

### Configurer le webhook chez Scorimmo

Une fois votre endpoint déployé, transmettez les informations suivantes à votre **account manager Scorimmo** (voir [Support](#support)) :

```
URL du webhook : https://votre-app.com/webhook/scorimmo

Authentification (au choix, fortement recommandé) :
  Option A - Signature HMAC-SHA256
    Header : X-Signature-256   (nom personnalisable)
    Valeur : sha256=<hex(hmac_sha256(rawBody, secret))>
    Secret : [votre SCORIMMO_WEBHOOK_SIGNATURE_SECRET]

  Option B - HTTP Basic auth via URL
    URL : https://user:pass@votre-app.com/webhook/scorimmo

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
SCORIMMO_EMAIL=api@votre-agence.fr
SCORIMMO_PASSWORD=votre-mot-de-passe
SCORIMMO_WEBHOOK_SIGNATURE_SECRET=votre-secret-hmac
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

**Étape 1 - Exclure la route du CSRF** dans `bootstrap/app.php` :

```php
$middleware->validateCsrfTokens(except: [
    config('scorimmo.webhook_path'),
]);
```

**Étape 2 - Écouter les événements** dans `AppServiceProvider` ou un `EventServiceProvider` :

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

**Étape 1 - Enregistrer le bundle** dans `config/bundles.php` :

```php
return [
    // ...
    Scorimmo\Bridge\Symfony\ScorimmoBundle::class => ['all' => true],
];
```

**Étape 2 - Créer** `config/packages/scorimmo.yaml` :

```yaml
scorimmo:
    email:                    '%env(SCORIMMO_EMAIL)%'
    password:                 '%env(SCORIMMO_PASSWORD)%'
    webhook_signature_secret: '%env(SCORIMMO_WEBHOOK_SIGNATURE_SECRET)%'
```

**Étape 3 - Ajouter dans** `.env` :

```env
SCORIMMO_EMAIL=api@votre-agence.fr
SCORIMMO_PASSWORD=votre-mot-de-passe
SCORIMMO_WEBHOOK_SIGNATURE_SECRET=votre-secret-hmac
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

### `leads->get(int $id, array $include = []): array`

Retourne un lead complet par son ID Scorimmo.

- `$include` : relations à charger en même temps - `'customer'`, `'seller'`, `'appointments'`, `'reminders'`, `'requests'`, `'comments'`

### `leads->since(string|\DateTimeInterface $date, string $field = 'created_at', int $maxPages = 100, ?int $storeId = null, array $include = [], ?callable $onProgress = null): array`

Retourne tous les leads créés (ou modifiés) après `$date`. La pagination est gérée automatiquement - le résultat est un tableau plat dédupliqué.

- `$date` : `DateTimeInterface` (heure préservée) ou `string` au format `Y-m-d` (ex: `"2026-05-05"`), `Y-m-d H:i:s`, ou ISO 8601 (ex: `"2026-05-05T08:00:00+02:00"`)
- `$field` : `'created_at'` (défaut) ou `'updated_at'`
- `$maxPages` : plafond de pages récupérées (défaut : 100, soit ~10 000 leads avec `limit=100`)
- `$storeId` : restreint à un point de vente via le paramètre `store_id` ; `null` = tous les points de vente
- `$include` : relations à charger (ex: `['customer', 'seller']`)
- `$onProgress` : callback appelé après chaque page - `fn(int $page, int $count, int $total, array $meta)`

```php
$leads = $client->leads->since(
    new DateTime('-24 hours'),
    storeId: 776,
    include: ['customer', 'seller'],
    onProgress: function (int $page, int $count, int $total, array $meta): void {
        echo "Page {$page} - {$total} leads récupérés\n";
    },
);
```

### `leads->list(array $query = []): array`

Retourne une page de leads. Le tableau `$query` accepte :

| Paramètre | Type | Description |
|---|---|---|
| `page` | `int` | Numéro de page (défaut : 1) |
| `limit` | `int` | Résultats par page (défaut : 10, max : 100) |
| `sort` | `string` | Tri : `'champ:asc'` ou `'champ:desc'` - champs : `id`, `created_at`, `updated_at`, `status` |
| `include` | `string` | Relations : `'customer,seller,appointments'` |
| `store_id` | `int` | Restreindre à un point de vente |
| `seller_id` | `int` | Restreindre à un conseiller |
| `status` | `string` | Statut du lead |
| `substatus` | `string` | Sous-statut |
| `interest` | `string` | Type d'intérêt (`TRANSACTION`, `LOCATION`…) |
| `origin` | `string` | Origine du lead |
| `contact_type` | `string` | `'physical'`, `'phone'` ou `'digital'` |
| `customer_first_name` | `string` | Prénom du contact |
| `customer_last_name` | `string` | Nom du contact |
| `customer.email` | `string` | Email du contact |
| `customer.phone` | `string` | Téléphone du contact |
| `external_lead_id` | `string` | Référence externe du lead |
| `requests_reference` | `string` | Référence du bien |
| `ids` | `string` | IDs multiples séparés par virgule |

**Filtres de date avec opérateurs bracket :**

| Opérateur | Exemple |
|---|---|
| `[gte]` supérieur ou égal | `'updated_at[gte]' => '2024-06-01T00:00:00+00:00'` |
| `[lte]` inférieur ou égal | `'updated_at[lte]' => '2024-12-31T23:59:59+00:00'` |
| `[eq]` égalité | `'created_at[eq]' => '2024-06-15T00:00:00+00:00'` |

```php
// Combinaison de filtres
$result = $client->leads->list([
    'seller_id'        => 3533,
    'status'           => 'Affecté',
    'created_at[gte]'  => '2026-03-01T00:00:00+00:00',
    'sort'             => 'created_at:desc',
    'limit'            => 20,
]);
```

Retourne `['data' => [...], 'meta' => ['total_items' => ..., 'page' => ..., 'limit' => ...]]`.

---

## Référence — Ressources secondaires

Toutes les ressources ci-dessous exposent `list(array $query = [])` et `get(int $id)`.

### Rendez-vous - `appointments`

| Paramètre | Type | Description |
|---|---|---|
| `lead_id` | `int` | Filtrer par lead |
| `store_id` | `int` | Filtrer par point de vente |
| `canceled` | `bool` | `true` = annulés uniquement |
| `created_at[gte/lte/eq]` | `string` | Filtres de date de création (ISO 8601) |
| `sort` | `string` | `'id'`, `'created_at'`, `'start_time'` (avec `:asc`/`:desc`) |

### Commentaires - `comments`

| Paramètre | Type | Description |
|---|---|---|
| `lead_id` | `int` | Filtrer par lead |
| `store_id` | `int` | Filtrer par point de vente |
| `user_id` | `int` | Filtrer par auteur |
| `breadcrumb` | `string` | Filtrer par chemin d'activité |
| `created_at[gte/lte/eq]` | `string` | Filtres de date (ISO 8601) |
| `sort` | `string` | `'id'`, `'created_at'` (avec `:asc`/`:desc`) |

### Rappels - `reminders`

| Paramètre | Type | Description |
|---|---|---|
| `lead_id` | `int` | Filtrer par lead |
| `store_id` | `int` | Filtrer par point de vente |
| `canceled` | `bool` | `true` = annulés uniquement |
| `created_at[gte/lte/eq]` | `string` | Filtres de date (ISO 8601) |
| `sort` | `string` | `'id'`, `'created_at'`, `'start_time'` (avec `:asc`/`:desc`) |

### Demandes - `requests`

| Paramètre | Type | Description |
|---|---|---|
| `lead_id` | `int` | Filtrer par lead |
| `store_id` | `int` | Filtrer par point de vente |
| `type` | `string` | Type de bien |
| `created_at[gte/lte/eq]` | `string` | Filtres de date (ISO 8601) |
| `sort` | `string` | `'id'`, `'created_at'` (avec `:asc`/`:desc`) |

### Contacts - `customers`

| Paramètre | Type | Description |
|---|---|---|
| `email` | `string` | Recherche par email |
| `phone` | `string` | Recherche par téléphone |
| `zip_code` | `string` | Filtrer par code postal |
| `city` | `string` | Filtrer par ville |
| `sort` | `string` | `'id'`, `'last_name'`, `'created_at'` (avec `:asc`/`:desc`) |

### Origines - `origins`

| Paramètre | Type | Description |
|---|---|---|
| `store_id` | `int` | Filtrer par point de vente |
| `has_tracking` | `bool` | `true` = origines avec au moins un traceur actif |
| `tracking_channel` | `string` | `'phone'` ou `'email'` |
| `include` | `string` | `'tracking'` pour inclure les numéros/emails traceurs |

### Utilisateurs - `users`

| Paramètre | Type | Description |
|---|---|---|
| `store_id` | `int` | Filtrer par point de vente |
| `interest` | `string` | Filtrer par intérêt (`TRANSACTION`, `LOCATION`…) |
| `role` | `string` | `'admin'`, `'manager'`, `'agent'` ou `'virtual'` |
| `sort` | `string` | `'id'`, `'last_name'`, `'created_at'` (avec `:asc`/`:desc`) |

### Statuts - `status`

| Paramètre | Type | Description |
|---|---|---|
| `ids` | `string` | Liste d'ids séparés par virgule |
| `interest` | `string` | Liste CSV d'intérêts (ex: `'TRANSACTION,LOCATION'`) |
| `store_id` | `string` | Liste CSV d'ids de points de vente (ex: `'1,2,3'`) |

Réponse : `[{ label, sub_status[]|null }, …]`.

### Points de vente - `stores`, Champs additionnels - `additionalFields`, Champs de demande - `requestFields`

Liste paginée simple. Utilisez `store_id` / `interest` pour filtrer les champs. Aucun paramètre spécifique au-delà des filtres de pagination.

### Formulaires publics - `form`

Soumission d'un formulaire de contact qui crée un lead et envoie un email au destinataire. **Scope requis : `ROLE_API_FORM_WRITE`** (à demander séparément de `lead:write`).

```php
$response = $client->form->submit([
    'store_id'   => 776,
    'libelle_id' => 12,
    'to_email'   => 'contact@agence.fr',      // ou array de destinataires
    'origin'     => 'Site web',
    'message'    => 'Je souhaite visiter le bien X.',
    'subject'    => 'Demande de visite',       // optionnel
    'customer'   => [
        'civility'   => 'M.',
        'first_name' => 'Jean',
        'last_name'  => 'Dupont',
        'email'      => 'jean@example.com',
        'phone'      => '0612345678',
    ],
    'requests'          => [/* array de {label => valeur} */],
    'additional_fields' => [/* array de {label => valeur} */],
    'external_lead_id'  => 'CRM-12345',        // optionnel
]);
// $response = ['status' => 200, 'message' => 'email created', 'id' => 42, 'store_id' => 776, ...]
```

### Appels sortants - `webCallbacks`

Déclenche un appel depuis le PBX Scorimmo vers un numéro. **N'utilise pas** l'authentification Bearer : passez la clé personnelle `WebCallback` fournie par Scorimmo pour votre point de vente.

```php
$client->webCallbacks->launch('votre-cle-wcb', '+33612345678');
// ['results' => ['...'], 'information' => 200]
```

---

## Référence — Gestion des tokens

Le client gère automatiquement l'access token. À chaque expiration, il tente d'abord un refresh silencieux, puis bascule sur email/password si nécessaire.

### Cycle de vie recommandé

```php
// 1. Premier démarrage — authentification par identifiants
$client = new ScorimmoClient(email: '...', password: '...');

// 2. Forcer l'auth initiale et récupérer le refresh token
$client->getToken();
$refreshToken = $client->getRefreshToken();
// → persister $refreshToken (cache Redis, base de données, coffre-fort…)

// 3. Démarrages suivants — sans identifiants
$refreshToken = /* charger depuis le stockage */;
$client = new ScorimmoClient(refreshToken: $refreshToken);

// 4. Après chaque session, le refresh token a tourné — le re-persister
$client->getToken(); // déclenche le refresh si l'access token est expiré
$nouveauRefreshToken = $client->getRefreshToken();
// → mettre à jour le stockage
```

> **Rotation automatique :** chaque refresh token ne peut être utilisé qu'une seule fois. Le nouveau refresh token est disponible via `getRefreshToken()` après chaque renouvellement.

### `getRefreshToken(): ?string`

Retourne le refresh token courant, disponible après le premier appel authentifié.

### `refreshAccessToken(string $refreshToken): array`

Échange explicitement un refresh token contre une nouvelle paire de tokens et met à jour l'état interne du client.

```php
$tokens = $client->refreshAccessToken($refreshToken);
// $tokens['access_token'], $tokens['refresh_token'], $tokens['expires_at']
```

### `revokeToken(?string $refreshToken = null): array`

Révoque un refresh token spécifique, ou tous les refresh tokens du compte si `null`.

```php
$client->revokeToken($refreshToken); // révoque ce token
$client->revokeToken();              // révoque tous les tokens
```

### `validateToken(): array`

Valide l'access token courant et retourne ses métadonnées : `version`, `status`, `authenticated`, `scopes`, `stores`, `interests`.

---

## Référence — Événements webhook

### `new_lead` - Nouveau lead reçu

Déclenché à la création d'un lead. Le payload est l'objet lead complet.

| Champ | Type | Description |
|---|---|---|
| `id` | `int` | Identifiant Scorimmo du lead |
| `store_id` | `int` | Point de vente concerné |
| `interest` | `string` | `TRANSACTION`, `LOCATION`, `GESTION`… |
| `origin` | `string` | Source du lead (portail, transfert agence…) |
| `purpose` | `string` | Intention : `ACHAT`, `VENTE`, `LOCATION`… |
| `contact_type` | `string` | `physical`, `phone` ou `digital` |
| `status` | `string` | Statut initial du lead |
| `created_at` | `string` | Date ISO 8601 de création |
| `customer` | `array` | Contact : `first_name`, `last_name`, `email`, `phone`, `other_phone_number` |
| `seller` | `array\|null` | Conseiller assigné : `id`, `first_name`, `last_name`, `email` |
| `requests` | `array` | Biens recherchés ou proposés (liste) |
| `comments` | `array` | Commentaires initiaux (liste) |

```php
'new_lead' => function (array $lead): void {
    $name = trim($lead['customer']['first_name'] . ' ' . $lead['customer']['last_name']);
    // $lead['id'], $lead['store_id'], $lead['interest'], $lead['origin']
    // $lead['customer']['email'], $lead['customer']['phone']
    // $lead['seller']['id'] ?? null
},
```

### `update_lead` - Lead modifié

Déclenché à chaque modification d'un lead. Le payload contient **uniquement les champs modifiés** (merge partiel), jamais l'objet complet.

| Champ | Type | Description |
|---|---|---|
| `id` | `int` | Identifiant du lead modifié |
| `updated_at` | `string` | Date ISO 8601 de la modification |
| `external_lead_id` | `string` | Présent si modifié |
| `status` | `string` | Présent si le statut a changé |
| `seller` | `array` | Présent si le conseiller a changé |
| _(autres champs)_ | | Seuls les champs réellement modifiés sont inclus |

```php
'update_lead' => function (array $event): void {
    // $event['id'] est toujours présent
    // $event['status'] ?? null  - nouveau statut si changé
    // $event['seller']['id'] ?? null  - nouveau conseiller si réaffecté
},
```

### `new_comment` - Nouveau commentaire

Déclenché à l'ajout d'un commentaire ou d'une note sur un lead.

| Champ | Type | Description |
|---|---|---|
| `lead_id` | `int` | Identifiant du lead concerné |
| `comment` | `string` | Texte du commentaire |
| `created_at` | `string` | Date ISO 8601 |
| `external_lead_id` | `string\|null` | Référence CRM du lead, si renseignée |

```php
'new_comment' => function (array $event): void {
    // $event['lead_id'], $event['comment'], $event['created_at']
    // $event['external_lead_id'] ?? null
},
```

### `new_rdv` - Rendez-vous planifié

Déclenché à la création d'un rendez-vous sur un lead.

| Champ | Type | Description |
|---|---|---|
| `lead_id` | `int` | Identifiant du lead concerné |
| `start_time` | `string` | Date/heure ISO 8601 du rendez-vous |
| `location` | `string` | Lieu du rendez-vous |
| `detail` | `string` | Objet ou description du rendez-vous |
| `comment` | `string\|null` | Note libre |
| `external_lead_id` | `string\|null` | Référence CRM du lead, si renseignée |

```php
'new_rdv' => function (array $event): void {
    // $event['lead_id'], $event['start_time'], $event['location'], $event['detail']
},
```

### `new_reminder` - Rappel planifié

Déclenché à la création d'un rappel ou d'une relance sur un lead.

| Champ | Type | Description |
|---|---|---|
| `lead_id` | `int` | Identifiant du lead concerné |
| `start_time` | `string` | Date/heure ISO 8601 du rappel |
| `detail` | `string` | Objet du rappel |
| `comment` | `string\|null` | Note libre |
| `external_lead_id` | `string\|null` | Référence CRM du lead, si renseignée |

```php
'new_reminder' => function (array $event): void {
    // $event['lead_id'], $event['start_time'], $event['detail']
},
```

### `closure_lead` - Lead clôturé

Déclenché à la clôture d'un lead (succès commercial ou abandon).

| Champ | Type | Description |
|---|---|---|
| `lead_id` | `int` | Identifiant du lead clôturé |
| `status` | `string` | Libellé du statut de clôture : `Succès` (vente/location conclue), `Fermé` (abandonné), `Fermé par l'opérateur` |
| `close_reason` | `string\|null` | Motif de clôture (présent uniquement quand `status` = `Fermé` ou `Succès`) |
| `external_lead_id` | `string\|null` | Référence CRM du lead, si renseignée |

```php
'closure_lead' => function (array $event): void {
    // $event['lead_id'], $event['status'], $event['close_reason'] ?? null
    if ($event['status'] === 'Succès') {
        // Vente ou location conclue
    }
},
```

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