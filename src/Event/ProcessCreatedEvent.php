<?php

namespace Castor\Event;

use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

class ProcessCreatedEvent extends Event
{
    public function __construct(
        public readonly Process $process,
    ) {
    }
}
