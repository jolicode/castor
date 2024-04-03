<?php

namespace Castor\Listener;

use Castor\Stub\StubsGenerator;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

class GenerateStubsListener
{
    public function __construct(
        private readonly StubsGenerator $stubsGenerator,
    ) {
    }

    // Must be before the command is executed, because we have to check for many
    // command options
    #[AsEventListener()]
    public function generateStubs(ConsoleCommandEvent $event): void
    {
        if (class_exists(\RepackedApplication::class)) {
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
