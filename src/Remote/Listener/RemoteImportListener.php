<?php

namespace Castor\Remote\Listener;

use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Remote\Importer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/** @internal */
class RemoteImportListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly Importer $importer,
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
        $this->importer->fetchPackages($event->application->getInput());
    }
}
