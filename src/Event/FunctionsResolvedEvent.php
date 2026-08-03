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
     * @param string                      $mountPath              the filesystem path of the mount currently being resolved
     * @param bool                        $isRootMount            whether this is the root application (true) or a mounted one (false)
     */
    public function __construct(
        public array $taskDescriptors,
        public array $symfonyTaskDescriptors,
        public readonly string $mountPath = '',
        public readonly bool $isRootMount = true,
    ) {
    }
}
