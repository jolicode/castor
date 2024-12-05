<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AsPathArgument extends AsArgument
{
    public function __construct(
        ?string $name = null,
        string $description = '',
    ) {
        parent::__construct($name, $description);
    }
}
