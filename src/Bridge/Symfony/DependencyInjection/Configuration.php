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
                ->scalarNode('email')
                    ->isRequired()
                    ->info('Email de connexion API Scorimmo (identifiant du compte API)')
                ->end()
                ->scalarNode('password')
                    ->isRequired()
                    ->info('Mot de passe du compte API Scorimmo')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://pro.scorimmo.com')
                    ->info('URL de base de l\'instance Scorimmo')
                ->end()
                ->scalarNode('webhook_secret')
                    ->defaultNull()
                    ->info('Valeur du header d\'authentification pour les webhooks entrants')
                ->end()
                ->scalarNode('webhook_header')
                    ->defaultValue('X-Scorimmo-Key')
                    ->info('Nom du header d\'authentification webhook (configuré par point de vente)')
                ->end()
            ->end();

        return $tree;
    }
}
