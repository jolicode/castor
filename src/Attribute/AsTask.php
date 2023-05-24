<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    public function __construct(
        public string $name = '',
        public string|null $namespace = null,
        public string $description = ''
    ) {
    }
}
