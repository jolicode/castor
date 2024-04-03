<?php

namespace Castor;

/**
 * @internal
 */
class Mount
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $namespacePrefix = null,
    ) {
    }
}
