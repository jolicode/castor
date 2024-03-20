<?php

namespace Castor\Event;

use Castor\Console\Application;
use Castor\Descriptor\TaskDescriptorCollection;

class AfterApplicationInitializationEvent
{
    public function __construct(
        public readonly Application $application,
        public TaskDescriptorCollection $taskDescriptorCollection,
    ) {
    }
}
