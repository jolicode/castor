<?php

namespace Castor\Import\Listener;

use Castor\Event\AfterApplicationInitializationEvent;
use Castor\Import\Remote\PackageImporter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** @internal */
class RemoteImportListener
{
    public function __construct(
        private readonly PackageImporter $packageImporter,
    ) {
    }

    #[AsEventListener()]
    public function afterInitialize(AfterApplicationInitializationEvent $event): void
    {
        $this->packageImporter->fetchPackages();
    }
}
