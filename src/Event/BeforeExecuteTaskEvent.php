<?php

namespace Castor\Event;

use Castor\Attribute\AsTask;
use Castor\Console\Command\TaskCommand;

class BeforeExecuteTaskEvent
{
    public function __construct(
        public readonly TaskCommand $task,
        public readonly AsTask $taskAttribute,
    ) {
    }
}
