<?php

namespace Castor\Descriptor;

final readonly class DescriptorsCollection
{
    /**
     * @param list<ContextDescriptor>          $contextDescriptors
     * @param list<ContextGeneratorDescriptor> $contextGeneratorDescriptors
     * @param list<ListenerDescriptor>         $listenerDescriptors
     * @param list<TaskDescriptor>             $taskDescriptors
     * @param list<SymfonyTaskDescriptor>      $symfonyTaskDescriptors
     */
    public function __construct(
        public array $contextDescriptors,
        public array $contextGeneratorDescriptors,
        public array $listenerDescriptors,
        public array $taskDescriptors,
        public array $symfonyTaskDescriptors,
    ) {
    }
}
