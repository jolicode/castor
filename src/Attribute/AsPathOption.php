<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsPathOption extends AsOption
{
    /**
     * @param string|array<string>|null $shortcut
     * @param string|array<string>|null $filter
     */
    public function __construct(
        ?string $name = null,
        string|array|null $shortcut = null,
        ?int $mode = null,
        string $description = '',
        public readonly ?string $directory = null,
        public readonly string|array|null $filter = null,
    ) {
        parent::__construct($name, $shortcut, $mode, $description);
    }
}
