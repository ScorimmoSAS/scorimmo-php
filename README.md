# scorimmo-php

Official PHP SDK for the [Scorimmo](https://www.scorimmo.com) real-estate CRM platform.

## Requirements

- PHP ≥ 8.1
- ext-curl, ext-json

## Installation

```bash
composer require scorimmo/scorimmo-php
```

## API Client

```php
use Scorimmo\Client\ScorimmoClient;

$client = new ScorimmoClient(
    baseUrl: 'https://app.scorimmo.com',
    username: 'your-api-username',
    password: 'your-api-password',
);

// Fetch leads created in the last 24h (handles pagination automatically)
$leads = $client->leads->since(new DateTime('-24 hours'));

// Get a single lead
$lead = $client->leads->get(42);

// Search leads
$result = $client->leads->list([
    'search'  => ['external_lead_id' => 'CRM-001'],
    'order'   => 'desc',
    'limit'   => 20,
]);

// Create a lead
$created = $client->leads->create([
    'store_id' => 1,
    'interest' => 'TRANSACTION',
    'customer' => ['first_name' => 'Marie', 'last_name' => 'Dupont', 'phone' => '0600000001'],
    'properties' => [['type' => 'Appartement', 'price' => 250000]],
]);

// Update a lead (e.g. store your CRM id)
$client->leads->update($created['id'], ['external_lead_id' => 'CRM-456']);
```

## Webhook Handler

```php
use Scorimmo\Webhook\ScorimmoWebhook;

$webhook = new ScorimmoWebhook(
    headerValue: $_ENV['SCORIMMO_WEBHOOK_SECRET'],
    headerKey: 'X-Scorimmo-Key',
);

$headers = getallheaders();
$rawBody = file_get_contents('php://input');

$webhook->handle($headers, $rawBody, [
    'new_lead'     => fn(array $lead) => yourCRM()->createContact($lead),
    'update_lead'  => fn(array $e)    => yourCRM()->updateContact($e['id'], $e),
    'new_rdv'      => fn(array $e)    => yourCRM()->createAppointment($e),
    'closure_lead' => fn(array $e)    => yourCRM()->archiveContact($e['lead_id']),
]);

http_response_code(200);
```

### Webhook events

| Event | Trigger | Key fields |
|-------|---------|------------|
| `new_lead` | Lead created | Full lead object |
| `update_lead` | Lead updated | `id`, changed fields |
| `new_comment` | Comment added | `lead_id`, `comment` |
| `new_rdv` | Appointment created | `lead_id`, `start_time`, `location` |
| `new_reminder` | Reminder created | `lead_id`, `start_time` |
| `closure_lead` | Lead closed | `lead_id`, `status`, `close_reason` |

## Error handling

```php
use Scorimmo\Exception\ScorimmoApiException;
use Scorimmo\Exception\ScorimmoAuthException;

try {
    $lead = $client->leads->get(999);
} catch (ScorimmoApiException $e) {
    echo $e->getStatusCode(); // 404
    echo $e->getMessage();    // "Lead not found"
} catch (ScorimmoAuthException $e) {
    echo "Check your API credentials";
}
```

## License

MIT
