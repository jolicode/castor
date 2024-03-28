<?php

namespace Castor\Import\Listener;

use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Import\Remote\PackageImporter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** @internal */
class RemoteImportListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly PackageImporter $packageImporter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterApplicationInitializationEvent::class => 'afterInitialize',
        ];
    }

    public function afterInitialize(AfterApplicationInitializationEvent $event): void
    {
        $this->packageImporter->fetchPackages($event->application->getInput());
    }
}
