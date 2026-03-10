<?php

/**
 * Example: Fetch leads from the Scorimmo API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;

$client = new ScorimmoClient(
    baseUrl: $_ENV['SCORIMMO_URL'] ?? 'https://app.scorimmo.com',
    username: $_ENV['SCORIMMO_USER'] ?? '',
    password: $_ENV['SCORIMMO_PASSWORD'] ?? '',
);

// ── Fetch leads created in the last 24h ──────────────────────────────────────
$since = new DateTime('-24 hours');
$leads = $client->leads->since($since);

echo "Found " . count($leads) . " new leads\n";

foreach ($leads as $lead) {
    $name = trim(($lead['customer']['first_name'] ?? '') . ' ' . ($lead['customer']['last_name'] ?? '?'));
    echo "  → #{$lead['id']} {$name} — {$lead['interest']} — {$lead['status']}\n";
}

// ── Get a specific lead ───────────────────────────────────────────────────────
try {
    $lead = $client->leads->get(42);
    echo "\nLead #42: " . json_encode($lead, JSON_PRETTY_PRINT) . "\n";
} catch (ScorimmoApiException $e) {
    if ($e->getStatusCode() === 404) {
        echo "Lead #42 not found\n";
    } else {
        throw $e;
    }
}

// ── Create a lead ─────────────────────────────────────────────────────────────
$created = $client->leads->create([
    'store_id' => 1,
    'interest' => 'TRANSACTION',
    'origin'   => 'Mon Site',
    'customer' => [
        'first_name' => 'Marie',
        'last_name'  => 'Dupont',
        'email'      => 'marie.dupont@example.com',
        'phone'      => '0600000001',
    ],
    'properties' => [
        ['type' => 'Appartement', 'price' => 250000, 'area' => 65],
    ],
]);

echo "\nCreated lead #{$created['id']}\n";

// ── Update with your CRM id ───────────────────────────────────────────────────
$client->leads->update($created['id'], ['external_lead_id' => 'CRM-456']);
echo "Updated lead #{$created['id']} with external_lead_id CRM-456\n";
