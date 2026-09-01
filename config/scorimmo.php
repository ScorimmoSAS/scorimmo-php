<?php

return [
    /*
     | Identifiants API v2 fournis par votre account manager Scorimmo
     | ou disponibles dans votre espace pro.scorimmo.com.
     */
    'email'    => env('SCORIMMO_EMAIL'),
    'password' => env('SCORIMMO_PASSWORD'),
    'base_url' => env('SCORIMMO_URL', 'https://pro.scorimmo.com'),

    /*
     | Webhook — authentification par signature HMAC-SHA256.
     |
     | Scorimmo signe le corps brut de chaque requête webhook avec ce secret et l'envoie
     | dans un header dédié (par défaut : X-Signature-256, valeur "sha256=<hex>").
     | Le SDK vérifie la signature en temps constant.
     */
    'webhook_signature_secret' => env('SCORIMMO_WEBHOOK_SIGNATURE_SECRET'),
    'webhook_signature_header' => env('SCORIMMO_WEBHOOK_SIGNATURE_HEADER', 'X-Signature-256'),

    /*
     | Route exposée pour recevoir les webhooks Scorimmo.
     | Communiquez cette URL à Scorimmo : https://votre-app.com/{webhook_path}
     */
    'webhook_path' => env('SCORIMMO_WEBHOOK_PATH', 'webhook/scorimmo'),
];
