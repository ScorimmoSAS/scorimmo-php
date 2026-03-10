<?php

namespace Scorimmo\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('scorimmo');

        $tree->getRootNode()
            ->children()
                ->scalarNode('username')
                    ->isRequired()
                    ->info('Identifiant API Scorimmo')
                ->end()
                ->scalarNode('password')
                    ->isRequired()
                    ->info('Mot de passe API Scorimmo')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://pro.scorimmo.com')
                    ->info('URL de base de l\'instance Scorimmo')
                ->end()
                ->scalarNode('webhook_secret')
                    ->defaultNull()
                    ->info('Secret partagé pour authentifier les webhooks entrants')
                ->end()
                ->scalarNode('webhook_header')
                    ->defaultValue('X-Scorimmo-Key')
                    ->info('Nom de l\'en-tête d\'authentification webhook')
                ->end()
            ->end();

        return $tree;
    }
}
