<?php

use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Webhook\ScorimmoWebhook;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ScorimmoClient::class)
        ->args([
            '%scorimmo.email%',
            '%scorimmo.password%',
            '%scorimmo.base_url%',
        ])
        ->public();

    $services->set(ScorimmoWebhook::class)
        ->args([
            '%scorimmo.webhook_secret%',
            '%scorimmo.webhook_header%',
        ])
        ->public();
};
