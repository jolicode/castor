<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsOption extends AsCommandArgument
{
    /**
     * @param string|array<string>|null $shortcut
     * @param array<string>             $suggestedValues
     */
    public function __construct(
        string $name = null,
        public readonly string|array|null $shortcut = null,
        public readonly int|null $mode = null,
        public readonly string $description = '',
        public readonly array $suggestedValues = [],
    ) {
        parent::__construct($name);
    }
}
