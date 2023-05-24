<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    /**
     * @param array<string> $aliases
     */
    public function __construct(
        public string $name = '',
        public string|null $namespace = null,
        public string $description = '',
        public array $aliases = [],
    ) {
    }
}
