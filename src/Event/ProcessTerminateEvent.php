<?php

namespace Castor\Event;

use Symfony\Component\Process\Process;

class ProcessTerminateEvent
{
    public function __construct(
        public readonly Process $process,
    ) {
    }
}
