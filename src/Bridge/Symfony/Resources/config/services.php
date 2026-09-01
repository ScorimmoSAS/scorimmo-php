<?php

use Scorimmo\Client\ScorimmoClient;
use Scorimmo\Webhook\ScorimmoWebhook;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ScorimmoClient::class)
        ->args([
            '%scorimmo.email%',
            '%scorimmo.password%',
            '%scorimmo.base_url%',
        ])
        ->public();

    // Logger optionnel : si Monolog est présent, la balise `monolog.logger` (canal `scorimmo`)
    // fournit un logger dédié ; sinon on retombe silencieusement à null (nullOnInvalid).
    $services->set(ScorimmoWebhook::class)
        ->args([
            '%scorimmo.webhook_signature_secret%',
            '%scorimmo.webhook_signature_header%',
            service('logger')->nullOnInvalid(),
        ])
        ->tag('monolog.logger', ['channel' => 'scorimmo'])
        ->public();
};
