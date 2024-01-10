<?php

namespace Castor\Event;

use Castor\Console\Command\TaskCommand;

class AfterExecuteTaskEvent
{
    public function __construct(
        public readonly TaskCommand $task,
        public readonly mixed $result,
    ) {
    }
}
