<?php

use Illuminate\Support\Facades\Route;
use Scorimmo\Bridge\Laravel\Http\ScorimmoWebhookController;

Route::post(config('scorimmo.webhook_path', 'webhook/scorimmo'), ScorimmoWebhookController::class)
    ->name('scorimmo.webhook');
