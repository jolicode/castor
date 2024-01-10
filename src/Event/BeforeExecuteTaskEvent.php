<?php

namespace Castor\Event;

use Castor\Console\Command\TaskCommand;

class BeforeExecuteTaskEvent
{
    public function __construct(
        public readonly TaskCommand $task,
    ) {
    }
}
