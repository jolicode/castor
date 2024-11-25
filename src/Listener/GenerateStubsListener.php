<?php

namespace Castor\Listener;

use Castor\Stub\StubsGenerator;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** @internal */
class GenerateStubsListener
{
    public function __construct(
        private readonly StubsGenerator $stubsGenerator,
        #[Autowire('%repacked%')]
        private readonly bool $repacked,
        #[Autowire('%generate_stubs%')]
        private readonly bool $generateStubs,
    ) {
    }

    // Must be before the command is executed, because we have to check for many
    // command options
    #[AsEventListener()]
    public function generateStubs(ConsoleCommandEvent $event): void
    {
        if ($this->repacked) {
            return;
        }

        if (!$this->generateStubs) {
            return;
        }

        $command = $event->getCommand();
        if (!$command) {
            return;
        }
        if ('_complete' === $command->getName()) {
            return;
        }

        $this->stubsGenerator->generateStubsIfNeeded();
    }
}
