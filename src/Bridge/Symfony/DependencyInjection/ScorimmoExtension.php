<?php

namespace Scorimmo\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class ScorimmoExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Injecte les paramètres résolus dans le conteneur
        $container->setParameter('scorimmo.email',          $config['email']);
        $container->setParameter('scorimmo.password',       $config['password']);
        $container->setParameter('scorimmo.base_url',       $config['base_url']);
        $container->setParameter('scorimmo.webhook_secret', $config['webhook_secret']);
        $container->setParameter('scorimmo.webhook_header', $config['webhook_header']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');
    }

    public function getAlias(): string
    {
        return 'scorimmo';
    }
}
