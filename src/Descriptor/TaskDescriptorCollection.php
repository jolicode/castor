<?php

namespace Castor\Descriptor;

class TaskDescriptorCollection
{
    /**
     * @param TaskDescriptor[]        $taskDescriptors
     * @param SymfonyTaskDescriptor[] $symfonyTaskDescriptors
     */
    public function __construct(
        public readonly array $taskDescriptors = [],
        public readonly array $symfonyTaskDescriptors = [],
    ) {
    }

    public function merge(self $other): self
    {
        return new self(
            [...$this->taskDescriptors, ...$other->taskDescriptors],
            [...$this->symfonyTaskDescriptors, ...$other->symfonyTaskDescriptors],
        );
    }
}
