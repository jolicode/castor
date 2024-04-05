<?php

namespace Castor\Import;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class Mount
{
    public function __construct(
        public readonly string $path,
        public readonly bool $allowEmptyEntrypoint = false,
        public readonly ?string $namespacePrefix = null,
    ) {
    }
}
