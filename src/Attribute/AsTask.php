<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsTask
{
    /**
     * @param array<string>                          $aliases
     * @param array<int, callable(int): (int|false)> $onSignals
     * @param ?callable-string                       $fingerprint
     */
    public function __construct(
        public string $name = '',
        public string|null $namespace = null,
        public string $description = '',
        public array $aliases = [],
        public array $onSignals = [],
        public ?string $fingerprint = null,
    ) {
    }
}
