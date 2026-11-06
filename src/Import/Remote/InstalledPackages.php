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
    /** @var list<string> */
    private array $vendorDirectories = [];

    /** @var array<string, string> The names of the packages, by directory */
    private array $packages = [];

    public function addVendorDirectory(string $vendorDirectory): void
    {
        $this->vendorDirectories[] = $vendorDirectory;

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
     * The files PHP loads have their symbolic links resolved, as for the
     * packages Composer links to their path repository.
     */
    private static function canonicalize(string $path): string
    {
        // A phar has no real path
        return Path::canonicalize(realpath($path) ?: $path);
    }
}
