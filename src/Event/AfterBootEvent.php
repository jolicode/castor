<?php

namespace Castor\Event;

use Castor\Console\Application;
use Symfony\Contracts\EventDispatcher\Event;

class AfterBootEvent extends Event
{
    public function __construct(
        public readonly Application $application,
    ) {
    }
}
