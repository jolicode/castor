<?php

namespace Castor\Import\Remote;

use Castor\Console\Application;
use Composer\Autoload\ClassLoader;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Semver\VersionParser;
use Symfony\Component\Filesystem\Path;

/**
 * The packages shipped with Castor: the vendor of the phar or of the static
 * binary, or the one of the Composer project Castor is installed in.
 *
 * They are loaded in the same PHP process as the remote packages of
 * castor.composer.json, and a class is loaded once, so a remote package cannot
 * bring its own copy of one of them: the process would run a mix of the two
 * versions. When Castor runs Composer, they are declared as replaced by the
 * root package, like the "replace" key of composer.json does. Composer then
 * resolves the remote packages against the versions Castor ships, does not
 * install them a second time, and fails early when a requirement cannot be
 * satisfied by the shipped version.
 *
 * @internal
 */
final class BundledPackages
{
    /** The virtual package a remote package can require, to declare the Castor versions it supports */
    public const VIRTUAL_PACKAGE = 'castor/castor';

    /** The key of "extra.castor" in castor.composer.json that opts out when set to false */
    public const EXTRA_KEY = 'replace-bundled-packages';

    /** @var array{replaces: array<string, string>, provides: array<string, string>}|null */
    private ?array $packages = null;

    /**
     * @param string|null $vendorDir     The vendor directory holding the packages, detected when null
     * @param string      $castorVersion The version standing for the virtual package
     */
    public function __construct(
        private readonly ?string $vendorDir = null,
        private readonly string $castorVersion = Application::VERSION,
    ) {
    }

    /**
     * @param array<string, mixed> $extra The "extra" key of castor.composer.json
     */
    public static function isReplaceEnabled(array $extra): bool
    {
        $castor = $extra['castor'] ?? [];

        return !\is_array($castor) || false !== ($castor[self::EXTRA_KEY] ?? true);
    }

    /**
     * The packages Castor ships, with their exact version, plus the virtual
     * package standing for Castor itself.
     *
     * @return array<string, string> package name => version
     */
    public function getReplaces(): array
    {
        return $this->getPackages()['replaces'];
    }

    /**
     * The virtual packages (like "psr/log-implementation") provided by the
     * packages Castor ships.
     *
     * @return array<string, string> package name => version constraint
     */
    public function getProvides(): array
    {
        return $this->getPackages()['provides'];
    }

    /**
     * Changes when the packages Castor ships change, like after a Castor update.
     */
    public function getHash(): string
    {
        return hash('xxh128', json_encode($this->getPackages(), \JSON_THROW_ON_ERROR));
    }

    /**
     * Declares the packages on the root package Composer resolves the remote
     * packages for. The entries of castor.composer.json for the same packages
     * are overridden.
     */
    public function applyTo(RootPackageInterface $package): void
    {
        $parser = new VersionParser();

        $replaces = $package->getReplaces();
        foreach ($this->getReplaces() as $name => $version) {
            $replaces[$name] = new Link($package->getName(), $name, $parser->parseConstraints($version), Link::TYPE_REPLACE, $version);
        }
        $package->setReplaces($replaces);

        $provides = $package->getProvides();
        foreach ($this->getProvides() as $name => $constraint) {
            $provides[$name] = new Link($package->getName(), $name, $parser->parseConstraints($constraint), Link::TYPE_PROVIDE, $constraint);
        }
        $package->setProvides($provides);
    }

    /**
     * @return array{replaces: array<string, string>, provides: array<string, string>}
     */
    private function getPackages(): array
    {
        if (null !== $this->packages) {
            return $this->packages;
        }

        $installed = $this->readInstalled();
        $rootName = $installed['root']['name'] ?? null;
        $replaces = [];
        $provides = [];

        foreach ($installed['versions'] ?? [] as $name => $package) {
            if ($name === $rootName) {
                continue;
            }

            if (isset($package['pretty_version'])) {
                // A real package, unless its files are left out: the phar
                // does not ship every package of the vendor
                if (isset($package['install_path']) && !is_dir(Path::canonicalize($package['install_path']))) {
                    continue;
                }

                $replaces[$name] = $package['pretty_version'];

                continue;
            }

            // A virtual package, provided or replaced by a real one
            if ($package['provided'] ?? []) {
                $provides[$name] = implode(' || ', $package['provided']);
            }
            if ($package['replaced'] ?? []) {
                $replaces[$name] = implode(' || ', $package['replaced']);
            }
        }

        $replaces[self::VIRTUAL_PACKAGE] = $this->getCastorVersion();

        ksort($replaces);
        ksort($provides);

        return $this->packages = ['replaces' => $replaces, 'provides' => $provides];
    }

    /**
     * The content of the installed.php file Composer writes in the vendor
     * directory, next to the autoloader.
     *
     * @return array{root?: array{name?: string}, versions?: array<string, array{pretty_version?: string, install_path?: string, provided?: list<string>, replaced?: list<string>}>}
     */
    private function readInstalled(): array
    {
        $vendorDir = $this->vendorDir ?? $this->findVendorDir();

        if (null === $vendorDir || !file_exists($file = $vendorDir . '/composer/installed.php')) {
            return [];
        }

        /** @var array{root?: array{name?: string}, versions?: array<string, array{pretty_version?: string, install_path?: string, provided?: list<string>, replaced?: list<string>}>} $installed */
        $installed = require $file;

        return $installed;
    }

    /**
     * The vendor directory of the autoloader Castor itself is loaded by: the
     * remote packages have their own autoloader, registered in front of it.
     */
    private function findVendorDir(): ?string
    {
        foreach (ClassLoader::getRegisteredLoaders() as $vendorDir => $loader) {
            if ($loader->findFile(self::class)) {
                return $vendorDir;
            }
        }

        return null;
    }

    private function getCastorVersion(): string
    {
        try {
            new VersionParser()->normalize($this->castorVersion);

            return $this->castorVersion;
        } catch (\UnexpectedValueException) {
            // A snapshot build, like "v1.7.0-14-g4531440": the release it is
            // based on
            return explode('-', $this->castorVersion, 2)[0];
        }
    }
}
