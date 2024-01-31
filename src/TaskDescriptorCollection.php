<?php

namespace Castor;

class TaskDescriptorCollection
{
    /**
     * @param TaskDescriptor[]        $taskDescriptors
     * @param SymfonyTaskDescriptor[] $symfonyTaskDescriptors
     */
    public function __construct(
        public readonly array $taskDescriptors,
        public readonly array $symfonyTaskDescriptors,
    ) {
    }
}
