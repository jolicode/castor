<?php

namespace Castor\Attribute;

abstract class AsCommandArgument
{
    public function __construct(
        public readonly string|null $name = null,
    ) {}
}
