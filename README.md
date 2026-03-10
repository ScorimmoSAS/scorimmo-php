# scorimmo-php

SDK officiel PHP pour la plateforme CRM immobilier [Scorimmo](https://pro.scorimmo.com).

Inclut les intégrations natives **Laravel** et **Symfony**.

---

## Installation

```bash
composer require scorimmo/scorimmo-php
```

**Prérequis :** PHP ≥ 8.1, ext-curl, ext-json

---

## Client API (PHP natif)

```php
use Scorimmo\Client\ScorimmoClient;

$client = new ScorimmoClient(
    username: 'votre-identifiant-api',
    password: 'votre-mot-de-passe-api',
    // baseUrl: 'https://pro.scorimmo.com' (par défaut)
);

// Leads des dernières 24h (pagination automatique)
$leads = $client->leads->since(new DateTime('-24 hours'));

// Récupérer un lead
$lead = $client->leads->get(42);

// Rechercher
$result = $client->leads->list([
    'search' => ['external_lead_id' => 'MON-CRM-001'],
    'order'  => 'desc',
    'limit'  => 20,
]);
```

---

## Intégration Laravel

### Configuration

```bash
php artisan vendor:publish --tag=scorimmo-config
```

Dans votre `.env` :

```env
SCORIMMO_USERNAME=votre-identifiant-api
SCORIMMO_PASSWORD=votre-mot-de-passe-api
SCORIMMO_WEBHOOK_SECRET=votre-secret-webhook
```

### Client via injection de dépendances

```php
use Scorimmo\Client\ScorimmoClient;

class LeadController extends Controller
{
    public function __construct(private ScorimmoClient $scorimmo) {}

    public function index()
    {
        return $this->scorimmo->leads->since(now()->subDay());
    }
}
```

### Réception de webhooks

Le package enregistre automatiquement la route `/webhook/scorimmo`.

Excluez-la du CSRF dans `bootstrap/app.php` :

```php
$middleware->validateCsrfTokens(except: [
    config('scorimmo.webhook_path'),
]);
```

Écoutez les événements dans `AppServiceProvider` :

```php
Event::listen('scorimmo.new_lead',    fn($lead) => Contact::createFromScorimmo($lead));
Event::listen('scorimmo.update_lead', fn($e)    => Contact::updateFromScorimmo($e['id'], $e));
Event::listen('scorimmo.closure_lead',fn($e)    => Contact::archiveFromScorimmo($e['lead_id']));
```

---

## Intégration Symfony

### Configuration

Enregistrez le bundle dans `config/bundles.php` :

```php
Scorimmo\Bridge\Symfony\ScorimmoBundle::class => ['all' => true],
```

Créez `config/packages/scorimmo.yaml` :

```yaml
scorimmo:
    username: '%env(SCORIMMO_USERNAME)%'
    password: '%env(SCORIMMO_PASSWORD)%'
    webhook_secret: '%env(SCORIMMO_WEBHOOK_SECRET)%'
```

### Client via autowiring

```php
use Scorimmo\Client\ScorimmoClient;

class LeadController extends AbstractController
{
    public function __construct(private ScorimmoClient $scorimmo) {}

    #[Route('/leads')]
    public function index(): JsonResponse
    {
        return $this->json(
            $this->scorimmo->leads->since(new \DateTime('-24 hours'))
        );
    }
}
```

### Réception de webhooks

```php
use Scorimmo\Webhook\ScorimmoWebhook;

#[Route('/webhook/scorimmo', methods: ['POST'])]
public function webhook(Request $request, ScorimmoWebhook $webhook): JsonResponse
{
    $event = $webhook->parse($request->headers->all(), $request->getContent());
    $this->dispatcher->dispatch(new ScorimmoEvent($event), 'scorimmo.' . $event['event']);
    return $this->json(['ok' => true]);
}
```

Écoutez avec un listener :

```php
#[AsEventListener(event: 'scorimmo.new_lead')]
class NewLeadListener
{
    public function __invoke(ScorimmoEvent $event): void
    {
        // Créer le contact dans votre CRM
    }
}
```

---

## Événements disponibles

| Événement | Déclencheur | Champs principaux |
|-----------|-------------|-------------------|
| `new_lead` | Nouveau lead créé | Objet lead complet |
| `update_lead` | Lead modifié | `id`, champs modifiés |
| `new_comment` | Commentaire ajouté | `lead_id`, `comment` |
| `new_rdv` | Rendez-vous créé | `lead_id`, `start_time`, `location` |
| `new_reminder` | Rappel créé | `lead_id`, `start_time` |
| `closure_lead` | Lead clôturé | `lead_id`, `status`, `close_reason` |

---

## Transmettre l'URL webhook à Scorimmo

Communiquez les informations suivantes à votre **account manager Scorimmo** ou à **assistance@scorimmo.com** :

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

## Gestion des erreurs

```php
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;
use Scorimmo\Exception\WebhookAuthException;
use Scorimmo\Exception\WebhookValidationException;

try {
    $lead = $client->leads->get(999);
} catch (ScorimmoApiException $e) {
    echo $e->getStatusCode(); // 404
    echo $e->getMessage();
} catch (ScorimmoAuthException $e) {
    echo 'Vérifiez vos identifiants API';
}
```

---

## Support

- Account manager Scorimmo
- **assistance@scorimmo.com**
- [pro.scorimmo.com](https://pro.scorimmo.com)
