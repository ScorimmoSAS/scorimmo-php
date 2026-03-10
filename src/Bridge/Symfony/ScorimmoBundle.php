<?php

namespace Scorimmo\Bridge\Symfony;

use Scorimmo\Bridge\Symfony\DependencyInjection\ScorimmoExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class ScorimmoBundle extends AbstractBundle
{
    public function getContainerExtension(): ScorimmoExtension
    {
        return new ScorimmoExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }
}
