<?php

namespace Castor\Import;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
final readonly class Mount
{
    /**
     * @param ?string           $package        The name of the package, for a Composer package
     * @param list<string>|null $files          The files to load instead of the entrypoint, like the ones of the
     *                                          features of a plugin
     * @param bool              $exposeAutoload Whether the tasks, contexts and listeners of the files the package
     *                                          autoloads are exposed
     */
    public function __construct(
        public string $path,
        public bool $allowEmptyEntrypoint = false,
        public ?string $namespacePrefix = null,
        public bool $allowRemotePackage = true,
        public ?string $file = null,
        public ?string $workingDirectory = null,
        public ?string $package = null,
        public ?array $files = null,
        public bool $exposeAutoload = true,
    ) {
    }
}
