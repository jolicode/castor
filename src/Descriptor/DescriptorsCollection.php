<?php

namespace Castor\Descriptor;

final class DescriptorsCollection
{
    /**
     * @param list<ContextDescriptor>          $contextDescriptors
     * @param list<ContextGeneratorDescriptor> $contextGeneratorDescriptors
     * @param list<ListenerDescriptor>         $listenerDescriptors
     * @param list<TaskDescriptor>             $taskDescriptors
     * @param list<SymfonyTaskDescriptor>      $symfonyTaskDescriptors
     */
    public function __construct(
        public readonly array $contextDescriptors,
        public readonly array $contextGeneratorDescriptors,
        public readonly array $listenerDescriptors,
        public readonly array $taskDescriptors,
        public readonly array $symfonyTaskDescriptors,
    ) {
    }
}
