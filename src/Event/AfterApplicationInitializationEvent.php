<?php

namespace Castor\Event;

use Castor\Console\Application;
use Castor\Descriptor\TaskDescriptorCollection;

/**
 * @deprecated since Castor 0.16, use FunctionsResolvedEvent instead
 */
class AfterApplicationInitializationEvent
{
    public function __construct(
        public readonly Application $application,
        public TaskDescriptorCollection $taskDescriptorCollection,
    ) {
    }
}
