<?php

namespace Castor\Descriptor;

use Castor\Attribute\AsTask;

class TaskDescriptor
{
    public function __construct(
        public readonly AsTask $taskAttribute,
        public readonly \ReflectionFunction $function,
        public ?string $workingDirectory = null,
    ) {
    }
}
