<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsPathArgument extends AsArgument
{
    /**
     * @param string|array<string>|null $filter
     */
    public function __construct(
        ?string $name = null,
        string $description = '',
        public readonly ?string $directory = null,
        public readonly string|array|null $filter = null,
    ) {
        parent::__construct($name, $description);
    }
}
