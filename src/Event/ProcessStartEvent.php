<?php

namespace Castor\Event;

use Symfony\Component\Process\Process;

class ProcessStartEvent
{
    public function __construct(
        public readonly Process $process,
    ) {
    }
}
