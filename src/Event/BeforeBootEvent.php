<?php

namespace Castor\Event;

use Castor\Console\Application;

/** @internal */
class BeforeBootEvent
{
    public function __construct(
        public readonly Application $application,
    ) {
    }
}
