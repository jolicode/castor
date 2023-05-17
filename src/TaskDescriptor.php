<?php

namespace Castor;

use Castor\Attribute\Task;

class TaskDescriptor
{
    public function __construct(
        public readonly Task $taskAttribute,
        public readonly \ReflectionFunction $function,
    ) {
    }
}
