<?php

namespace Castor\💩;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// See https://github.com/symfony/symfony/issues/60765
class FixLazyServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (class_exists(\RepackedApplication::class)) {
            $container->getDefinition('.lazy.Castor\Console\Application')->setClass(\RepackedApplication::class);
        }
    }
}
