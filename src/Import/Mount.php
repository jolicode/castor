<?php

namespace Castor\Import;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
final readonly class Mount
{
    public function __construct(
        public string $path,
        public bool $allowEmptyEntrypoint = false,
        public ?string $namespacePrefix = null,
        public bool $allowRemotePackage = true,
        public ?string $file = null,
        public ?string $workingDirectory = null,
    ) {
    }
}
