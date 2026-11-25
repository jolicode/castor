<?php

namespace Castor\Import;

use Castor\Console\Input\CommandLine;
use Castor\Import\Remote\Composer;
use Castor\Kernel;
use Symfony\Component\Finder\Finder;

use function Castor\Internal\castor_require;

/** @internal */
class Importer
{
    /**
     * @var array<string, true>
     */
    private array $imports = [];

    public function __construct(
        private readonly Composer $composer,
        private readonly Kernel $kernel,
        private readonly CommandLine $commandLine,
    ) {
    }

    public function import(string $path, ?string $file = null): void
    {
        if ($this->composer->isPackage($path)) {
            $package = $this->composer->getPackageName($path);

            if (null === $file) {
                $this->deprecate(\sprintf('Importing a package with "import(\'%s\')" is deprecated, use "register_plugin(\'%s\')" instead: it loads the tasks, contexts and listeners of the files the package autoloads, or of the "default" feature it declares, but no longer its castor.php file.', $path, $package));
            } else {
                $this->deprecate(\sprintf('Importing a file of a package with "import(\'%s\', file: \'%s\')" is deprecated, use "register_plugin(\'%s\', \'<feature>\')" instead: ask the author of the package to declare this file in a feature, in the "extra.castor.features" section of its composer.json file.', $path, $file, $package));
            }

            if ($packageDirectory = $this->composer->resolvePackage($path, $file)) {
                $this->kernel->addMount(new Mount($packageDirectory, allowEmptyEntrypoint: true, allowRemotePackage: false, file: $file, package: $package));
            }

            return;
        }

        // PHP would look for a stream wrapper for any other "scheme://" path
        if (str_contains($path, '://')) {
            throw new \InvalidArgumentException(\sprintf('The scheme "%s://" is not supported, only "composer://" is.', strstr($path, '://', true)));
        }

        if (null !== $file) {
            throw new \InvalidArgumentException(\sprintf('The "file" argument can only be used with a package, "%s" is a local path.', $path));
        }

        if (!file_exists($path)) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not exist.', $path));
        }

        if (is_file($path)) {
            $this->importFile($path);
        }

        if (is_dir($path)) {
            $files = Finder::create()
                ->files()
                ->name('*.php')
                ->notPath('vendor')
                ->in($path)
            ;

            foreach ($files as $file) {
                $this->importFile($file->getPathname());
            }
        }
    }

    public function importFile(string $file): void
    {
        if (isset($this->imports[$file])) {
            return;
        }
        $this->imports[$file] = true;

        castor_require($file);
    }

    /**
     * @return list<string>
     */
    public function getImports(): array
    {
        return array_keys($this->imports);
    }

    private function deprecate(string $message): void
    {
        // Nothing to change in the castor.php of a repacked application, and
        // the deprecation would land in an output meant to be parsed
        if (class_exists(\RepackedApplication::class) || $this->commandLine->isOutputMachineReadable()) {
            return;
        }

        trigger_deprecation('jolicode/castor', '1.10.0', $message);
    }
}
