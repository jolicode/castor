<?php

namespace Castor\Listener;

use Castor\Stub\StubsGenerator;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GenerateStubsListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly StubsGenerator $stubsGenerator,
        private readonly string $rootDir,
    ) {
    }

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

        $this->stubsGenerator->generateStubsIfNeeded($this->rootDir . '/.castor.stub.php');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must be before the command is executed, because we have to check
            // for many command options
            ConsoleEvents::COMMAND => 'generateStubs',
        ];
    }
}
