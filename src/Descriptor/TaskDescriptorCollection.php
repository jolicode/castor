<?php

namespace Castor\Descriptor;

trigger_deprecation('castor', '0.16', 'The "%s" class is deprecated, use "%s" instead.', TaskDescriptorCollection::class, DescriptorsCollection::class);

/**
 * @deprecated since Castor 0.16, use DescriptorsCollection instead
 */
class TaskDescriptorCollection
{
    /**
     * @param list<TaskDescriptor>        $taskDescriptors
     * @param list<SymfonyTaskDescriptor> $symfonyTaskDescriptors
     */
    public function __construct(
        public readonly array $taskDescriptors = [],
        public readonly array $symfonyTaskDescriptors = [],
    ) {
    }
}
