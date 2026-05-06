<?php

/**
 * Example: Fetch leads from the Scorimmo API v2
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Exception\ScorimmoApiException;

$client = new ScorimmoClient(
    email:    $_ENV['SCORIMMO_EMAIL'] ?? '',
    password: $_ENV['SCORIMMO_PASSWORD'] ?? '',
    baseUrl:  $_ENV['SCORIMMO_URL'] ?? 'https://pro.scorimmo.com',
);

// ── Leads des dernières 24h (toutes pages, avec customer et seller chargés) ──
$since = new DateTime('-24 hours');
$leads = $client->leads->since($since, include: ['customer', 'seller']);

echo "Found " . count($leads) . " new leads\n";

foreach ($leads as $lead) {
    $name = trim(($lead['customer']['first_name'] ?? '') . ' ' . ($lead['customer']['last_name'] ?? '?'));
    echo "  → #{$lead['id']} {$name} — {$lead['interest']} — {$lead['status']}\n";
}

// ── Leads d'un point de vente spécifique ─────────────────────────────────────
$storeLeads = $client->leads->since(
    date: new DateTime('-7 days'),
    storeId: 42,
    include: ['customer'],
);
echo "\nStore #42: " . count($storeLeads) . " leads\n";

// ── Récupérer un lead par son ID (avec toutes ses relations) ─────────────────
try {
    $lead = $client->leads->get(42, include: ['customer', 'seller', 'appointments', 'comments']);
    echo "\nLead #42: " . json_encode($lead, JSON_PRETTY_PRINT) . "\n";
} catch (ScorimmoApiException $e) {
    if ($e->getStatusCode() === 404) {
        echo "Lead #42 not found\n";
    } else {
        throw $e;
    }
}

// ── Mise à jour partielle d'un lead ──────────────────────────────────────────
$client->leads->update(42, ['external_lead_id' => 'CRM-456']);
echo "Updated lead #42 with external_lead_id CRM-456\n";

// ── Lister les leads avec filtres avancés ────────────────────────────────────
$filtered = $client->leads->list([
    'interest'         => 'Transaction',
    'store_id'         => 1,
    'created_at[gte]'  => '2026-01-01T00:00:00+00:00',
    'sort'             => 'created_at:desc',
    'limit'            => 20,
    'include'          => 'customer',
]);
echo "\nFiltered: " . count($filtered['data']) . " leads (total: {$filtered['meta']['total_items']})\n";

// ── Ressources de référence ───────────────────────────────────────────────────
$stores = $client->stores->list();
echo "\nStores accessibles :\n";
foreach ($stores['data'] as $store) {
    echo "  → #{$store['id']} {$store['name']} ({$store['city']})\n";
}

$statuses = $client->status->list(['limit' => 100]);
echo "\nStatuts disponibles : " . $statuses['meta']['total_items'] . "\n";

// ── Gestion des tokens (optionnel) ────────────────────────────────────────────
// Récupérer le refresh token pour le persister côté appelant
$client->getToken(); // force la première auth
$refreshToken = $client->getRefreshToken();
echo "\nRefresh token: " . substr($refreshToken, 0, 8) . "...\n";

// Renouveler l'access token sans les credentials
$newTokens = $client->refreshAccessToken($refreshToken);
echo "New access token expires at: {$newTokens['expires_at']}\n";

// Valider le token courant et voir ses scopes
$tokenInfo = $client->validateToken();
echo "Scopes: " . implode(', ', $tokenInfo['scopes']) . "\n";
