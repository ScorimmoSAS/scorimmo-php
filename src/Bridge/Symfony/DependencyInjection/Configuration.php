<?php

namespace Scorimmo\Bridge\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Arbre de configuration du bundle `scorimmo` :
 *  - credentials API (email, password, base_url) — requis uniquement si ScorimmoClient est utilisé
 *  - webhook (webhook_signature_secret, webhook_signature_header) — requis uniquement si ScorimmoWebhook est utilisé
 *
 * Tous les nœuds sont optionnels pour permettre un usage « API seule » ou « webhook seul ».
 * La validation effective est reportée à l'exécution par le service qui consomme la valeur.
 */
class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('scorimmo');

        $tree->getRootNode()
            ->children()
                ->scalarNode('email')
                    ->defaultNull()
                    ->info('Email de connexion API Scorimmo (identifiant du compte API v2). Requis pour utiliser ScorimmoClient.')
                ->end()
                ->scalarNode('password')
                    ->defaultNull()
                    ->info('Mot de passe du compte API Scorimmo. Requis pour utiliser ScorimmoClient.')
                ->end()
                ->scalarNode('base_url')
                    ->defaultValue('https://pro.scorimmo.com')
                    ->info('URL de base de l\'instance Scorimmo')
                ->end()
                ->scalarNode('webhook_signature_secret')
                    ->defaultNull()
                    ->info('Secret HMAC-SHA256 partagé avec Scorimmo pour vérifier la signature des webhooks entrants. Requis pour utiliser ScorimmoWebhook.')
                ->end()
                ->scalarNode('webhook_signature_header')
                    ->defaultValue('X-Signature-256')
                    ->info('Nom du header portant la signature HMAC (valeur au format "sha256=<hex>")')
                ->end()
            ->end();

        return $tree;
    }
}
