<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    /**
     * @param array<string>                          $aliases
     * @param array<int, callable(int): (int|false)> $onSignals
     */
    public function __construct(
        public string $name = '',
        public ?string $namespace = null,
        public string $description = '',
        public array $aliases = [],
        public array $onSignals = [],
        public string|bool $enabled = true,
        public bool $ignoreValidationErrors = false,
        public bool $default = false,
    ) {
    }
}
