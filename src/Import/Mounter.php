<?php

namespace Castor\Import;

use Castor\Console\Input\CommandLine;
use Castor\Import\Remote\Composer;
use Castor\Kernel;

/** @internal */
class Mounter
{
    public function __construct(
        private readonly Composer $composer,
        private readonly Kernel $kernel,
        private readonly CommandLine $commandLine,
    ) {
    }

    public function mount(string $path, ?string $namespacePrefix = null, ?string $file = null): void
    {
        if ($this->composer->isPackage($path)) {
            $package = $this->composer->getPackageName($path);
            $replacement = null === $namespacePrefix ? \sprintf("register_plugin('%s')", $package) : \sprintf("register_plugin('%s', prefix: '%s')", $package, $namespacePrefix);

            // Nothing to change in the castor.php of a repacked application, and
            // the deprecation would land in an output meant to be parsed
            if (!class_exists(\RepackedApplication::class) && !$this->commandLine->isOutputMachineReadable()) {
                trigger_deprecation('jolicode/castor', '1.10.0', \sprintf('Mounting a package with "mount(\'%s\')" is deprecated, use "%s" instead: its tasks then run in the working directory of the context instead of the directory of the package (use "__DIR__" in the package for its own files), and its castor.php file is no longer loaded unless the package declares it in a feature.', $path, $replacement));
            }

            if ($packageDirectory = $this->composer->resolvePackage($path, $file)) {
                $this->kernel->addMount(new Mount($packageDirectory, allowEmptyEntrypoint: true, namespacePrefix: $namespacePrefix, allowRemotePackage: false, file: $file, workingDirectory: $packageDirectory, package: $package));
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

        // Like the root, unless remote packages are disabled ("--no-remote")
        $this->kernel->addMount(new Mount($path, namespacePrefix: $namespacePrefix, allowRemotePackage: $this->composer->isRemoteAllowed(), file: $file, workingDirectory: $path));
    }
}
