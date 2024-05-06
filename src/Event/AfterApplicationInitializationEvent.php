<?php

namespace Castor\Event;

use Castor\Console\Application;
use Castor\Descriptor\TaskDescriptorCollection;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated since Castor 0.16, use FunctionsResolvedEvent instead
 */
class AfterApplicationInitializationEvent extends Event
{
    public function __construct(
        public readonly Application $application,
        public TaskDescriptorCollection $taskDescriptorCollection,
    ) {
    }
}
