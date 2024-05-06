<?php

namespace Castor\Event;

use Castor\Descriptor\SymfonyTaskDescriptor;
use Castor\Descriptor\TaskDescriptor;
use Symfony\Contracts\EventDispatcher\Event;

class FunctionsResolvedEvent extends Event
{
    /**
     * @param list<TaskDescriptor>        $taskDescriptors
     * @param list<SymfonyTaskDescriptor> $symfonyTaskDescriptors
     */
    public function __construct(
        public array $taskDescriptors,
        public array $symfonyTaskDescriptors,
    ) {
    }
}
