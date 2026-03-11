<?php

namespace Scorimmo\Bridge\Laravel;

use Illuminate\Support\ServiceProvider;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Webhook\ScorimmoWebhook;

class ScorimmoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/scorimmo.php', 'scorimmo');

        $this->app->singleton(ScorimmoClient::class, function () {
            return new ScorimmoClient(
                username: config('scorimmo.username'),
                password: config('scorimmo.password'),
                baseUrl:  config('scorimmo.base_url', 'https://pro.scorimmo.com'),
            );
        });

        $this->app->singleton(ScorimmoWebhook::class, function () {
            return new ScorimmoWebhook(
                headerValue: config('scorimmo.webhook_secret'),
                headerKey:   config('scorimmo.webhook_header', 'X-Scorimmo-Key'),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/scorimmo.php' => config_path('scorimmo.php'),
            ], 'scorimmo-config');
        }

        $this->loadRoutesFrom(__DIR__ . '/../../../routes/webhook.php');
    }
}
