# Changelog

Les versions suivent le [versionnage sémantique](https://semver.org/lang/fr/). À chaque release, le numéro de cette page, `ScorimmoClient::VERSION` et le tag git `vX.Y.Z` doivent correspondre.

La version du SDK est distincte de `X-Scorimmo-Version`, qui date le contrat de l'API.

## [1.1.0] - 2026-11-09

Première version taguée de ce SDK.

### Ajouté

- Campagnes : `campaigns`, `campaignFields`, `campaignContacts`, dont `campaignContacts->inject()`.
- Abonnements webhook : `webhooks` (`list`, `get`, `errors`, `create`, `update`, `delete`).
- Réception des événements `campaign_contact_updated`, `campaign_contact_closed` et `campaign_closed`.
- Authentification OAuth : classe `ScorimmoOAuth` et paramètre `oauthClientId` de `ScorimmoClient`.
- Refresh tokens : `listTokens()`, `deleteToken()` et paramètre `tokenName`.
- `ScorimmoApiException` expose `$body`, `$retryAfter` et `getFieldErrors()`.
- En-tête `User-Agent: scorimmo-php/<version>` et constante `ScorimmoClient::VERSION`.

### Corrigé

- `revokeToken()` envoie le Bearer exigé par l'API, qui répondait toujours 401.
- Les messages d'erreur des routes `/auth/*` et `/oauth/*` ne sont plus remplacés par `HTTP <code>`.

### Modifié

- `form->submit()` : `customer.civility` devient `customer.title`. L'ancienne clé est encore convertie par le SDK.

### Documentation

- `to_email` n'accepte qu'une seule adresse.
- `validateToken()` retourne `stores_id`.
- Le filtre `external_lead_id` de `leads->list()` est retiré : le serveur l'ignore.
- Limite de 5 refresh tokens actifs par compte.
