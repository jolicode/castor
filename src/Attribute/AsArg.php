<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsArg
{
    /**
     * @param string|array<string>|null $shortcut
     */
    public function __construct(
        public string|null $name = null,
        public string|null $description = null,
        public string|array|null $shortcut = null,
    ) {
    }
}
