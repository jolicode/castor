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
        public string|null $name = null,
        public string|array|null $shortcut = null,
        public int|null $mode = null,
        public string $description = '',
        public array $suggestedValues = [],
    ) {
    }
}
