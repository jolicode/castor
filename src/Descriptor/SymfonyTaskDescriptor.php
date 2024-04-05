<?php

namespace Castor\Descriptor;

use Castor\Attribute\AsSymfonyTask;

class SymfonyTaskDescriptor
{
    /**
     * @param mixed[] $definition
     */
    public function __construct(
        public readonly AsSymfonyTask $taskAttribute,
        public readonly \ReflectionClass $function,
        public readonly array $definition,
    ) {
    }
}
