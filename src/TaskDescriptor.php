<?php

namespace Castor;

use Castor\Attribute\AsTask;

class TaskDescriptor
{
    public function __construct(
        public readonly AsTask $taskAttribute,
        public readonly \ReflectionFunction $function,
    ) {
    }
}
