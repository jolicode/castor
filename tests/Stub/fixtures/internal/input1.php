<?php

namespace Test;

use Castor\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** @internal */
final class Kernel
{
    public function __construct(
        #[Autowire(lazy: true)]
        public readonly Application $application,
    )
    {
    }

    public function boot(InputInterface $input, OutputInterface $output): void
    {
        // some code
    }
}
