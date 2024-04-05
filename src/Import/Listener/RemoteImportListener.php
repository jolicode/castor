<?php

namespace Castor\Import\Listener;

use Castor\Event\BeforeApplicationInitializationEvent;
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
    public function fetchPackages(BeforeApplicationInitializationEvent $event): void
    {
        $this->packageImporter->fetchPackages();
    }
}
