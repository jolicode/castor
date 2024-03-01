<?php

namespace Castor\Attribute;

abstract class AsCommandArgument
{
    public function __construct(
        public readonly ?string $name = null,
    ) {
    }
}
