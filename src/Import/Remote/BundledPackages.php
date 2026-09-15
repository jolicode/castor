<?php

namespace Castor\Import\Remote;

use Castor\Console\Application;
use Composer\Autoload\ClassLoader;
use Composer\Repository\PackageRepository;
use Composer\Repository\RepositoryInterface;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Symfony\Component\Filesystem\Path;

/**
 * The packages shipped with Castor: the vendor of the phar or of the static
 * binary, or the one of the Composer project Castor is installed in.
 *
 * They are loaded in the same PHP process as the remote packages of
 * castor.composer.json, and a class is loaded once, so a remote package cannot
 * bring its own copy of one of them: the process would run a mix of the two
 * versions. When Castor runs Composer, they are declared in a repository of
 * metapackages (packages without files) placed before every other repository,
 * so their versions are the only ones Composer knows for these packages: the
 * remote packages are resolved against the versions Castor ships, nothing is
 * installed a second time, and a requirement the shipped version does not
 * satisfy fails early.
 *
 * @internal
 */
final class BundledPackages
{
    /**
     * The package of Castor itself, listed at the running version: a remote
     * package depending on it (to declare the Castor versions it supports)
     * is resolved against the running Castor, instead of installing a second
     * one in the vendor of the remote packages.
     */
    public const CASTOR_PACKAGE = 'jolicode/castor';

    /** The key of "extra.castor" in castor.composer.json that opts out when set to false */
    public const EXTRA_KEY = 'bundled-packages';

    /** The key of "extra.castor" marking a metapackage of the repository, kept in the lock file */
    public const EXTRA_BUNDLED_KEY = 'bundled';

    /** @var array{versions: array<string, string>, provides: array<string, string>}|null */
    private ?array $packages = null;

    /**
     * @param string|null $vendorDir     The vendor directory holding the packages, detected when null
     * @param string      $castorVersion The version of Castor itself
     */
    public function __construct(
        private readonly ?string $vendorDir = null,
        private readonly string $castorVersion = Application::VERSION,
    ) {
    }

    /**
     * @param array<string, mixed> $extra The "extra" key of castor.composer.json
     */
    public static function isEnabled(array $extra): bool
    {
        $castor = $extra['castor'] ?? [];

        return !\is_array($castor) || false !== ($castor[self::EXTRA_KEY] ?? true);
    }

    /**
     * Whether a package definition (like an entry of the lock file) is a
     * metapackage of the repository, written by this Castor or another one.
     *
     * @param array<string, mixed> $package
     */
    public static function isBundled(array $package): bool
    {
        $extra = $package['extra'] ?? [];
        $castor = \is_array($extra) ? ($extra['castor'] ?? []) : [];

        return \is_array($castor) && true === ($castor[self::EXTRA_BUNDLED_KEY] ?? false);
    }

    /**
     * The packages of a lock file that do not match the packages Castor ships,
     * to update: a real package Castor now ships, a metapackage of the
     * repository for a package Castor no longer ships (a plain install would
     * keep it, with no files behind), or one at another version that no
     * longer satisfies the constraints of castor.composer.json and of the
     * locked packages. A metapackage at another version that still does is
     * left alone, so the lock does not change with every Castor release.
     *
     * @param array<string, mixed> $lock         The decoded castor.composer.lock
     * @param array<string, mixed> $composerJson The decoded castor.composer.json
     *
     * @return list<string>
     */
    public function findOutdatedInLock(array $lock, array $composerJson): array
    {
        $versions = $this->getVersions();
        $packages = [];

        foreach (['packages', 'packages-dev'] as $key) {
            foreach (\is_array($lock[$key] ?? null) ? $lock[$key] : [] as $package) {
                if (\is_array($package) && \is_string($package['name'] ?? null)) {
                    $packages[] = $package;
                }
            }
        }

        // The constraints on the bundled packages: the ones of
        // castor.composer.json, and the ones of the locked packages (whose
        // dev requirements do not count, like for Composer)
        $requires = [];
        $conflicts = [];
        $definitions = [[$composerJson, ['require', 'require-dev', 'conflict']]];
        foreach ($packages as $package) {
            $definitions[] = [$package, ['require', 'conflict']];
        }

        foreach ($definitions as [$definition, $keys]) {
            foreach ($keys as $key) {
                foreach (\is_array($definition[$key] ?? null) ? $definition[$key] : [] as $name => $constraint) {
                    if (!\is_string($name) || !\is_string($constraint) || !isset($versions[$name])) {
                        continue;
                    }

                    if ('conflict' === $key) {
                        $conflicts[$name][] = $constraint;
                    } else {
                        $requires[$name][] = $constraint;
                    }
                }
            }
        }

        $outdated = [];

        foreach ($packages as $package) {
            $name = $package['name'];

            if (!self::isBundled($package)) {
                // A real package, locked before Castor shipped it
                if (isset($versions[$name])) {
                    $outdated[] = $name;
                }

                continue;
            }

            if (!isset($versions[$name])) {
                $outdated[] = $name;

                continue;
            }

            if ($versions[$name] !== ($package['version'] ?? null) && !self::satisfies($versions[$name], $requires[$name] ?? [], $conflicts[$name] ?? [])) {
                $outdated[] = $name;
            }
        }

        return $outdated;
    }

    /**
     * Changes when the packages Castor ships change, like after a Castor update.
     */
    public function getHash(): string
    {
        return hash('xxh128', json_encode($this->getPackages(), \JSON_THROW_ON_ERROR));
    }

    /**
     * A repository of metapackages standing for the packages Castor ships, to
     * place before the other repositories of castor.composer.json.
     */
    public function createRepository(): RepositoryInterface
    {
        $definitions = [];

        foreach ($this->getVersions() as $name => $version) {
            // The mark ends up in the lock file, to tell these entries from
            // the packages of the other repositories when Castor changes
            $definition = [
                'name' => $name,
                'version' => $version,
                'type' => 'metapackage',
                'extra' => ['castor' => [self::EXTRA_BUNDLED_KEY => true]],
            ];

            if (self::CASTOR_PACKAGE === $name) {
                // What the shipped packages provide is declared on Castor itself
                $definition['provide'] = $this->getProvides();
            }

            $definitions[] = $definition;
        }

        return new class(['package' => $definitions], $this->castorVersion) extends PackageRepository {
            /**
             * @param array{package: list<array<string, mixed>>} $config
             */
            public function __construct(
                array $config,
                private readonly string $castorVersion,
            ) {
                parent::__construct($config);
            }

            /**
             * Names the repository in the Composer messages, like the one
             * explaining that a constraint cannot be satisfied.
             */
            public function getRepoName(): string
            {
                return \sprintf('the packages bundled with Castor %s', $this->castorVersion);
            }
        };
    }

    /**
     * The packages Castor ships, with their exact version, plus Castor itself.
     *
     * @return array<string, string> package name => version
     */
    private function getVersions(): array
    {
        return $this->getPackages()['versions'];
    }

    /**
     * The virtual packages (like "psr/log-implementation") provided by the
     * packages Castor ships.
     *
     * @return array<string, string> package name => version constraint
     */
    private function getProvides(): array
    {
        return $this->getPackages()['provides'];
    }

    /**
     * @return array{versions: array<string, string>, provides: array<string, string>}
     */
    private function getPackages(): array
    {
        if (null !== $this->packages) {
            return $this->packages;
        }

        $installed = $this->readInstalled();
        $rootName = $installed['root']['name'] ?? null;
        $versions = [];
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

                $versions[$name] = $package['pretty_version'];

                continue;
            }

            // A virtual package, provided by a real one
            if ($package['provided'] ?? []) {
                $provides[$name] = implode(' || ', $package['provided']);
            }
        }

        // Castor is the root package of the phar and of the static binary, and
        // a regular package of a Composer project it is installed in
        $versions[self::CASTOR_PACKAGE] = $this->getCastorVersion();

        ksort($versions);
        ksort($provides);

        return $this->packages = ['versions' => $versions, 'provides' => $provides];
    }

    /**
     * The content of the installed.php file Composer writes in the vendor
     * directory, next to the autoloader.
     *
     * @return array{root?: array{name?: string}, versions?: array<string, array{pretty_version?: string, install_path?: string, provided?: list<string>}>}
     */
    private function readInstalled(): array
    {
        $vendorDir = $this->vendorDir ?? $this->findVendorDir();

        if (null === $vendorDir || !file_exists($file = $vendorDir . '/composer/installed.php')) {
            return [];
        }

        /** @var array{root?: array{name?: string}, versions?: array<string, array{pretty_version?: string, install_path?: string, provided?: list<string>}>} $installed */
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

    /**
     * @param list<string> $requires  The constraints the version must match
     * @param list<string> $conflicts The constraints the version must not match
     */
    private static function satisfies(string $version, array $requires, array $conflicts): bool
    {
        try {
            foreach ($requires as $constraint) {
                if (!Semver::satisfies($version, $constraint)) {
                    return false;
                }
            }

            foreach ($conflicts as $constraint) {
                if (Semver::satisfies($version, $constraint)) {
                    return false;
                }
            }
        } catch (\UnexpectedValueException) {
            // An odd version or constraint: let Composer decide
            return false;
        }

        return true;
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
