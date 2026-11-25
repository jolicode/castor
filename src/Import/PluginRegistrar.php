<?php

namespace Castor\Import;

use Castor\Import\Exception\ImportError;
use Castor\Import\Remote\Composer;
use Castor\Import\Remote\InstalledPackages;
use Castor\Kernel;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * Exposes the tasks, contexts and listeners of a plugin, a Composer package
 * required in castor.composer.json: the ones of the files it autoloads, its
 * "default" feature, or the ones of the features it declares in the
 * "extra.castor.features" section of its composer.json file.
 *
 * @internal
 */
class PluginRegistrar
{
    public const DEFAULT_FEATURE = 'default';

    public const FEATURES_EXTRA_KEY = 'features';

    /**
     * The plugins registered, by name, with their prefix and the features
     * already loaded.
     *
     * @var array<string, array{prefix: ?string, features: array<string, true>}>
     */
    private array $plugins = [];

    public function __construct(
        private readonly Composer $composer,
        private readonly InstalledPackages $installedPackages,
        private readonly Kernel $kernel,
    ) {
    }

    /**
     * @param string|list<string>|null $features
     */
    public function register(string $package, string|array|null $features = null, ?string $prefix = null): void
    {
        if (str_contains($package, '://')) {
            throw new \InvalidArgumentException(\sprintf('The plugin "%s" must be the name of a package, like "vendor/package", without "%s".', $package, strstr($package, '://', true) . '://'));
        }

        if (!preg_match('#^[^/\s]+/[^/\s]+$#', $package)) {
            throw new \InvalidArgumentException(\sprintf('The plugin "%s" must be the name of a package, like "vendor/package".', $package));
        }

        $features = $this->normalizeFeatures($package, $features);

        if (isset($this->plugins[$package]) && $this->plugins[$package]['prefix'] !== $prefix) {
            throw new ImportError(\sprintf('The plugin "%s" is already registered %s, it cannot be registered %s too: the tasks of a package are only loaded once.', $package, self::describePrefix($this->plugins[$package]['prefix']), self::describePrefix($prefix)));
        }

        if (!$packageDirectory = $this->composer->findInstalledPackage($package)) {
            return;
        }

        $declaredFeatures = $this->getDeclaredFeatures($package);

        foreach ($features as $feature) {
            if (self::DEFAULT_FEATURE !== $feature && !isset($declaredFeatures[$feature])) {
                $available = array_unique([self::DEFAULT_FEATURE, ...array_keys($declaredFeatures)]);

                throw new ImportError(\sprintf('The plugin "%s" has no "%s" feature, available features: "%s".', $package, $feature, implode('", "', $available)));
            }
        }

        $this->plugins[$package] ??= ['prefix' => $prefix, 'features' => []];
        $files = [];
        $exposeAutoload = false;

        foreach ($features as $feature) {
            if (isset($this->plugins[$package]['features'][$feature])) {
                continue;
            }

            $this->plugins[$package]['features'][$feature] = true;

            if (!isset($declaredFeatures[$feature])) {
                // The "default" feature, when the package does not declare it
                $exposeAutoload = true;

                continue;
            }

            foreach ($this->resolveFiles($package, $packageDirectory, $feature, $declaredFeatures[$feature]) as $file) {
                $files[$file] = true;
            }
        }

        if (!$files && !$exposeAutoload) {
            return;
        }

        $this->kernel->addMount(new Mount(
            $packageDirectory,
            allowEmptyEntrypoint: true,
            namespacePrefix: $prefix,
            allowRemotePackage: false,
            package: $package,
            files: array_keys($files),
            exposeAutoload: $exposeAutoload,
        ));
    }

    /**
     * @param string|list<string>|null $features
     *
     * @return list<string>
     */
    private function normalizeFeatures(string $package, string|array|null $features): array
    {
        if (null === $features) {
            return [self::DEFAULT_FEATURE];
        }

        $features = (array) $features;

        if (!$features) {
            throw new \InvalidArgumentException(\sprintf('The features of the plugin "%s" cannot be empty, leave them out to load its "%s" feature.', $package, self::DEFAULT_FEATURE));
        }

        foreach ($features as $feature) {
            // @phpstan-ignore function.alreadyNarrowedType (not enforced at runtime for the items of a list)
            if (!\is_string($feature) || '' === $feature) {
                throw new \InvalidArgumentException(\sprintf('The features of the plugin "%s" must be non empty strings.', $package));
            }
        }

        return array_values(array_unique($features));
    }

    /**
     * The features declared in the "extra.castor.features" section of the
     * composer.json file of the package, with their paths.
     *
     * @return array<string, list<string>>
     */
    private function getDeclaredFeatures(string $package): array
    {
        $definition = $this->installedPackages->getDefinition($package) ?? [];
        $extra = $definition['extra'] ?? [];
        $castor = \is_array($extra) ? ($extra['castor'] ?? []) : [];
        $features = \is_array($castor) ? ($castor[self::FEATURES_EXTRA_KEY] ?? []) : [];

        $invalid = new ImportError(\sprintf('The "extra.castor.%s" section of the composer.json file of the plugin "%s" must be an object, whose keys are the names of the features, and whose values are lists of paths relative to the package.', self::FEATURES_EXTRA_KEY, $package));

        if (!\is_array($features)) {
            throw $invalid;
        }

        $declared = [];

        foreach ($features as $name => $paths) {
            if (!\is_string($name) || '' === $name || !\is_array($paths) || !array_is_list($paths) || !$paths) {
                throw $invalid;
            }

            foreach ($paths as $path) {
                if (!\is_string($path) || '' === $path) {
                    throw $invalid;
                }
            }

            /* @var list<string> $paths */
            $declared[$name] = $paths;
        }

        return $declared;
    }

    /**
     * The PHP files of a feature: its files, and the ones of its directories.
     *
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function resolveFiles(string $package, string $packageDirectory, string $feature, array $paths): array
    {
        $realPackageDirectory = realpath($packageDirectory) ?: $packageDirectory;
        $autoloadedFiles = $this->getAutoloadedFiles($package, $realPackageDirectory);
        $files = [];

        foreach ($paths as $path) {
            $realPath = realpath($realPackageDirectory . '/' . $path);

            if (false === $realPath) {
                throw new ImportError(\sprintf('The path "%s" of the "%s" feature of the plugin "%s" does not exist.', $path, $feature, $package));
            }

            if (!Path::isBasePath($realPackageDirectory, $realPath)) {
                throw new ImportError(\sprintf('The path "%s" of the "%s" feature of the plugin "%s" is outside of the package.', $path, $feature, $package));
            }

            if (is_file($realPath)) {
                $featureFiles = [$realPath];
            } else {
                $featureFiles = [];

                foreach (Finder::create()->files()->name('*.php')->notPath('vendor')->in($realPath)->sortByName() as $file) {
                    $featureFiles[] = $file->getRealPath() ?: $file->getPathname();
                }
            }

            foreach ($featureFiles as $file) {
                if (isset($autoloadedFiles[$file])) {
                    throw new ImportError(\sprintf('The file "%s" of the "%s" feature of the plugin "%s" is also in the "autoload" section of its composer.json file: Composer loads it with the package, so the feature could not load its tasks, contexts and listeners. Remove it from the "autoload" section.', Path::makeRelative($file, $realPackageDirectory), $feature, $package));
                }

                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * The files Composer loads with the package, from the "files" section of
     * its autoload.
     *
     * @return array<string, true>
     */
    private function getAutoloadedFiles(string $package, string $packageDirectory): array
    {
        $definition = $this->installedPackages->getDefinition($package) ?? [];
        $autoload = $definition['autoload'] ?? [];
        $files = [];

        if (!\is_array($autoload)) {
            return [];
        }

        foreach ((array) ($autoload['files'] ?? []) as $path) {
            if (\is_string($path) && false !== $realPath = realpath($packageDirectory . '/' . $path)) {
                $files[$realPath] = true;
            }
        }

        return $files;
    }

    private static function describePrefix(?string $prefix): string
    {
        return null === $prefix ? 'without prefix' : \sprintf('with the prefix "%s"', $prefix);
    }
}
