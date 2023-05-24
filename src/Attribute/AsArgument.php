<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsArgument extends AsCommandArgument
{
    /**
     * @param array<string> $suggestedValues
     */
    public function __construct(
        string|null $name = null,
        public readonly string $description = '',
        public readonly array $suggestedValues = [],
    ) {
        parent::__construct($name);
    }
}
