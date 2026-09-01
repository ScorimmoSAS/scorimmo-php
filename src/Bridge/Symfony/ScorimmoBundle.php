<?php

namespace Scorimmo\Bridge\Symfony;

use Scorimmo\Bridge\Symfony\DependencyInjection\ScorimmoExtension;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Bundle Symfony `scorimmo` — inscrit {@see ScorimmoExtension} qui expose ScorimmoClient
 * et ScorimmoWebhook comme services publics du conteneur.
 */
class ScorimmoBundle extends AbstractBundle
{
    public function getContainerExtension(): ScorimmoExtension
    {
        return new ScorimmoExtension();
    }
}
