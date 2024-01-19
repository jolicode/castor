<?php

namespace Castor;

use Castor\Attribute\AsSymfonyTask;

/** @internal */
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
