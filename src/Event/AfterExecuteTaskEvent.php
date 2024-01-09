<?php

namespace Castor\Event;

use Castor\Attribute\AsTask;
use Castor\Console\Command\TaskCommand;

class AfterExecuteTaskEvent
{
    public function __construct(
        public readonly TaskCommand $task,
        public readonly AsTask $taskAttribute,
        public readonly mixed $result,
    ) {
    }
}
