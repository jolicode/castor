<?php

namespace Castor\Event;

use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

class ProcessStartEvent extends Event
{
    public function __construct(
        public readonly Process $process,
    ) {
    }
}
