<?php

namespace Castor\Event;

use Castor\Console\Application;
use Symfony\Contracts\EventDispatcher\Event;

/** @internal */
class BeforeBootEvent extends Event
{
    public function __construct(
        public readonly Application $application,
    ) {
    }
}
