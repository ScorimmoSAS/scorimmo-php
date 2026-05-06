<?php

namespace Scorimmo\Bridge\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Webhook\ScorimmoWebhook;

class ScorimmoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../../config/scorimmo.php', 'scorimmo');

        $this->app->singleton(ScorimmoClient::class, function (Application $app) {
            return new ScorimmoClient(
                email:    Config::get('scorimmo.email'),
                password: Config::get('scorimmo.password'),
                baseUrl:  Config::get('scorimmo.base_url', 'https://pro.scorimmo.com'),
                logger:   $app->make(\Psr\Log\LoggerInterface::class),
            );
        });

        $this->app->singleton(ScorimmoWebhook::class, function () {
            return new ScorimmoWebhook(
                headerValue: Config::get('scorimmo.webhook_secret'),
                headerKey:   Config::get('scorimmo.webhook_header', 'X-Scorimmo-Key'),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../../config/scorimmo.php' => $this->app->configPath('scorimmo.php'),
            ], 'scorimmo-config');
        }

        $this->loadRoutesFrom(__DIR__ . '/../../../routes/webhook.php');
    }
}
