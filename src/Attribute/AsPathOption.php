<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsPathOption extends AsOption
{
    /**
     * @param string|array<string>|null $shortcut
     */
    public function __construct(
        ?string $name = null,
        string|array|null $shortcut = null,
        ?int $mode = null,
        string $description = '',
    ) {
        parent::__construct($name, $shortcut, $mode, $description);
    }
}
