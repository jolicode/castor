<?php

namespace Castor\Event;

use Symfony\Component\Process\Process;

class ProcessCreatedEvent
{
    public function __construct(
        public readonly Process $process,
    ) {
    }
}
