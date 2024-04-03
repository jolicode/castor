<?php

namespace Castor;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * @internal
 */
#[Exclude]
class Mount
{
    public function __construct(
        public readonly string $path,
        public readonly ?string $namespacePrefix = null,
    ) {
    }
}
