<?php

namespace Castor\Import\Remote;

use Symfony\Component\Filesystem\Path;

/**
 * The packages of the vendor directories whose autoloader Castor loaded, to
 * know which package a function or a class comes from: requiring a package
 * makes its code available, only importing it exposes its tasks, contexts and
 * listeners.
 *
 * @internal
 */
final class InstalledPackages
{
    private const EXPOSE_WITHOUT_IMPORT_EXTRA_KEY = 'expose-without-import';

    /** @var list<string> */
    private array $vendorDirectories = [];

    /** @var array<string, string> The names of the packages, by directory */
    private array $packages = [];

    /**
     * The packages exposing their tasks, contexts and listeners without being
     * imported, by name, read once asked for.
     *
     * @var array<string, true>|null
     */
    private ?array $packagesExposedWithoutImport = null;

    public function addVendorDirectory(string $vendorDirectory): void
    {
        $this->vendorDirectories[] = $vendorDirectory;
        $this->packagesExposedWithoutImport = null;

        if (!file_exists($file = $vendorDirectory . '/composer/installed.php')) {
            return;
        }

        /** @var array{root?: array{name?: string}, versions?: array<string, array{install_path?: string|null}>} $installed */
        $installed = require $file;
        $rootName = $installed['root']['name'] ?? null;

        foreach ($installed['versions'] ?? [] as $name => $package) {
            // The root package is the castor.composer.json file itself, and a
            // metapackage, like the ones of the packages bundled with Castor,
            // or a virtual package has no directory
            if ($name === $rootName || null === ($package['install_path'] ?? null)) {
                continue;
            }

            $this->packages[self::canonicalize($package['install_path'])] = $name;
        }
    }

    /**
     * The vendor directories, in the order their autoloader was loaded.
     *
     * @return list<string>
     */
    public function getVendorDirectories(): array
    {
        return $this->vendorDirectories;
    }

    /**
     * The package the file belongs to, or null when it belongs to the mount:
     * it is in no package, or it is in the mount directory and the package
     * holds this directory, like a package required from its own repository,
     * or from an example inside it, through a path repository.
     */
    public function findPackage(string $file, string $mountDirectory): ?string
    {
        $file = self::canonicalize($file);
        $packageDirectory = null;

        foreach ($this->packages as $directory => $name) {
            // The deepest one, for a package installed inside another one
            if (Path::isBasePath($directory, $file) && \strlen($directory) > \strlen($packageDirectory ?? '')) {
                $packageDirectory = $directory;
            }
        }

        if (null === $packageDirectory) {
            return null;
        }

        $mountDirectory = self::canonicalize($mountDirectory);

        if (Path::isBasePath($packageDirectory, $mountDirectory) && Path::isBasePath($mountDirectory, $file)) {
            return null;
        }

        return $this->packages[$packageDirectory];
    }

    /**
     * Whether the composer.json file of the package sets
     * "extra.castor.expose-without-import" to true: its tasks, contexts and
     * listeners are then meant to be loaded as soon as it is required.
     */
    public function isExposedWithoutImport(string $package): bool
    {
        if (null === $this->packagesExposedWithoutImport) {
            $this->packagesExposedWithoutImport = [];

            foreach ($this->vendorDirectories as $vendorDirectory) {
                // Unlike installed.php, it has the "extra" key of the packages
                if (!file_exists($file = $vendorDirectory . '/composer/installed.json')) {
                    continue;
                }

                /** @var array{packages?: list<array{name: string, extra?: array<string, mixed>}>} $installed */
                $installed = json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR);

                foreach ($installed['packages'] ?? [] as $definition) {
                    $castor = $definition['extra']['castor'] ?? null;

                    if (\is_array($castor) && true === ($castor[self::EXPOSE_WITHOUT_IMPORT_EXTRA_KEY] ?? false)) {
                        $this->packagesExposedWithoutImport[$definition['name']] = true;
                    }
                }
            }
        }

        return isset($this->packagesExposedWithoutImport[$package]);
    }

    /**
     * The files PHP loads have their symbolic links resolved, as for the
     * packages Composer links to their path repository.
     */
    private static function canonicalize(string $path): string
    {
        // A phar has no real path
        return Path::canonicalize(realpath($path) ?: $path);
    }
}
