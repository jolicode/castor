<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class Task
{
    public function __construct(
        public string $name = '',
        public string|null $namespace = null,
        public string $description = ''
    ) {
    }
}
