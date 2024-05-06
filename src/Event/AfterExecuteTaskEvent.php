<?php

namespace Castor\Event;

use Castor\Console\Command\TaskCommand;
use Symfony\Contracts\EventDispatcher\Event;

class AfterExecuteTaskEvent extends Event
{
    public function __construct(
        public readonly TaskCommand $task,
        public readonly mixed $result,
    ) {
    }
}
