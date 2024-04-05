<?php

namespace Castor\Event;

use Castor\Console\Application;

/** @internal */
class BeforeApplicationBootEvent
{
    public function __construct(
        public readonly Application $application,
    ) {
    }
}
