<?php

namespace Castor\Event;

use Castor\Console\Application;

/** @internal */
class BeforeApplicationInitializationEvent
{
    public function __construct(
        public readonly Application $application,
    ) {
    }
}
