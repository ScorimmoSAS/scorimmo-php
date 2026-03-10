<?php

return [
    /*
     | Identifiants API fournis par votre account manager Scorimmo
     | ou disponibles dans votre espace pro.scorimmo.com
     */
    'username' => env('SCORIMMO_USERNAME'),
    'password' => env('SCORIMMO_PASSWORD'),
    'base_url' => env('SCORIMMO_URL', 'https://pro.scorimmo.com'),

    /*
     | Webhook : secret partagé avec Scorimmo pour authentifier les appels entrants.
     | À communiquer à votre account manager ou à assistance@scorimmo.com.
     */
    'webhook_secret' => env('SCORIMMO_WEBHOOK_SECRET'),
    'webhook_header' => env('SCORIMMO_WEBHOOK_HEADER', 'X-Scorimmo-Key'),

    /*
     | Route exposée pour recevoir les webhooks Scorimmo.
     | Communiquez cette URL à Scorimmo : https://votre-app.com/{webhook_path}
     */
    'webhook_path' => env('SCORIMMO_WEBHOOK_PATH', 'webhook/scorimmo'),
];
