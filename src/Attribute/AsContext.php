<?php

namespace Castor\Attribute;

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class AsContext
{
    public function __construct(
        public string $name = '',
        public bool $default = false,
    ) {
    }
}
