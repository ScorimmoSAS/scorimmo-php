<?php

return [
    /*
     | Identifiants API fournis par votre account manager Scorimmo
     | ou disponibles dans votre espace pro.scorimmo.com
     |
     | Depuis l'API v2, l'identifiant est l'adresse email du compte API.
     */
    'email'    => env('SCORIMMO_EMAIL'),
    'password' => env('SCORIMMO_PASSWORD'),
    'base_url' => env('SCORIMMO_URL', 'https://pro.scorimmo.com'),

    /*
     | Webhook : secret partagé avec Scorimmo pour authentifier les appels entrants.
     | À communiquer à votre account manager ou à assistance@scorimmo.com.
     |
     | La clé du header (webhook_header) est configurable par point de vente ;
     | la valeur par défaut 'X-Scorimmo-Key' est utilisée si non précisée.
     */
    'webhook_secret' => env('SCORIMMO_WEBHOOK_SECRET'),
    'webhook_header' => env('SCORIMMO_WEBHOOK_HEADER', 'X-Scorimmo-Key'),

    /*
     | Route exposée pour recevoir les webhooks Scorimmo.
     | Communiquez cette URL à Scorimmo : https://votre-app.com/{webhook_path}
     */
    'webhook_path' => env('SCORIMMO_WEBHOOK_PATH', 'webhook/scorimmo'),
];
