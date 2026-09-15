<?php

namespace Castor\Import;

use Castor\Import\Remote\Composer;
use Castor\Kernel;

/** @internal */
class Mounter
{
    public function __construct(
        private readonly Composer $composer,
        private readonly Kernel $kernel,
    ) {
    }

    public function mount(string $path, ?string $namespacePrefix = null, ?string $file = null): void
    {
        if ($this->composer->isPackage($path)) {
            if ($packageDirectory = $this->composer->resolvePackage($path, $file)) {
                $this->kernel->addMount(new Mount($packageDirectory, namespacePrefix: $namespacePrefix, allowRemotePackage: false, file: $file, workingDirectory: $packageDirectory));
            }

            return;
        }

        // PHP would look for a stream wrapper for any other "scheme://" path
        if (str_contains($path, '://')) {
            throw new \InvalidArgumentException(\sprintf('The scheme "%s://" is not supported, only "composer://" is.', strstr($path, '://', true)));
        }

        if (!is_dir($path)) {
            throw new \InvalidArgumentException(\sprintf('The directory "%s" does not exist.', $path));
        }

        $this->kernel->addMount(new Mount($path, namespacePrefix: $namespacePrefix, file: $file, workingDirectory: $path));
    }
}
