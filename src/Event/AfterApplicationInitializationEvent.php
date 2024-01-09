<?php

namespace Castor\Event;

use Castor\Console\Application;
use Castor\TaskDescriptor;

class AfterApplicationInitializationEvent
{
    public function __construct(
        public readonly Application $application,
        /** @var array<TaskDescriptor> $tasks */
        public array &$tasks,
    ) {
    }
}
