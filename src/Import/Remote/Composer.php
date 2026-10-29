<?php

namespace Castor\Import\Remote;

use Castor\Console\Input\CommandLine;
use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Castor\Import\Exception\ImportError;
use Castor\Import\Exception\InvalidImportFormat;
use Composer\Installer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
class Composer
{
    public const VENDOR_DIR = '.castor/vendor';

    /**
     * The type of a package giving functions, tasks, contexts or listeners:
     * in its own repository, its composer.json lists its remote packages.
     */
    public const CASTOR_PLUGIN_TYPE = 'castor-plugin';

    /** Whether the remote packages were needed, but not installed, by a shell completion */
    private bool $skippedForCompletion = false;

    public function __construct(
        private readonly CommandLine $commandLine,
        private readonly OutputInterface $output,
        private readonly Filesystem $filesystem,
        private readonly BundledPackages $bundledPackages,
        private readonly InstalledPackages $installedPackages,
        #[Autowire('%composer_no_remote%')]
        private readonly bool $disableRemote,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function isRemoteAllowed(): bool
    {
        if ($this->disableRemote || $this->skippedForCompletion) {
            return false;
        }

        return !$this->commandLine->hasNoRemote();
    }

    public function install(string $entrypointDirectory): void
    {
        $update = $this->commandLine->hasUpdateRemotes();
        $displayProgress = !$this->commandLine->isOutputMachineReadable();

        if (!$composerJsonFile = $this->findComposerJsonFile($entrypointDirectory)) {
            $this->logger->debug(\sprintf('The castor.composer.json file does not exists in %s or %s/.castor, and %s is not a Castor plugin, skipping composer install.', $entrypointDirectory, $entrypointDirectory, $entrypointDirectory));

            return;
        }

        if (class_exists(\RepackedApplication::class)) {
            return;
        }

        $composerLockFile = \dirname($composerJsonFile) . '/castor.composer.lock';
        $vendorDirectory = $entrypointDirectory . '/' . self::VENDOR_DIR;

        if (!$update && $this->isInstalled($vendorDirectory, $composerLockFile)) {
            return;
        }

        // A shell completion must not download and run anything: the packages
        // already installed, even outdated, are used, and the completion goes
        // on without the remote imports when there is none
        if ($this->commandLine->isShellCompletion()) {
            $this->skippedForCompletion = !file_exists($vendorDirectory . '/autoload.php');
            $this->logger->debug('The remote packages are not installed during a shell completion.');

            return;
        }

        $this->filesystem->mkdir($vendorDirectory);
        $this->filesystem->dumpFile($vendorDirectory . '/.gitignore', "*\n");

        $progressIndicator = null;

        if ($displayProgress) {
            $progressIndicator = new ProgressIndicator($this->output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
            $progressIndicator->start('<comment>Downloading remote packages</comment>');
        }

        $args = [$update ? 'update' : 'install'];
        $outdatedPackages = $update ? [] : $this->getOutdatedBundledPackagesInLock($composerJsonFile, $composerLockFile);

        if ($outdatedPackages) {
            // The lock was written by another Castor version, or by Composer
            // itself, so it does not match the packages bundled with Castor for
            // these packages: only they are updated, so they follow the bundled
            // packages while the other packages keep their locked version
            $args = ['update', ...$outdatedPackages];
        }

        $this->run($composerJsonFile, $vendorDirectory, $args, callback: static function () use ($progressIndicator): void {
            $progressIndicator?->advance();
        });

        $progressIndicator?->finish('<info>Remote packages imported</info>');

        if ($outdatedPackages && $displayProgress) {
            $this->output->writeln(\sprintf('<comment>Updated in castor.composer.lock to follow the packages bundled with Castor: %s</comment>', implode(', ', $outdatedPackages)));
        }

        $this->writeInstalled($vendorDirectory, $composerLockFile);
    }

    /**
     * @param string|null $entrypointDirectory The directory of a mounted castor.php, as given to install(), or null for
     *                                         the root one
     */
    public function requireAutoload(?string $entrypointDirectory = null): void
    {
        if (null !== $entrypointDirectory) {
            $autoloadPath = $entrypointDirectory . '/' . self::VENDOR_DIR . '/autoload.php';
        } else {
            try {
                $autoloadPath = PathHelper::getCastorVendorDir() . '/autoload.php';
            } catch (\RuntimeException $e) {
                return;
            }
        }

        if (!file_exists($autoloadPath)) {
            return;
        }

        require $autoloadPath;

        $this->installedPackages->addVendorDirectory(\dirname($autoloadPath));
    }

    /**
     * The Composer file listing the remote packages of a directory: its
     * castor.composer.json, or the composer.json of the Castor plugin the
     * directory is the repository of.
     */
    public function findComposerJsonFile(string $directory): ?string
    {
        if (file_exists($file = $directory . '/castor.composer.json') || file_exists($file = $directory . '/.castor/castor.composer.json')) {
            return $file;
        }

        if ($this->isCastorPlugin($file = $directory . '/composer.json')) {
            return $file;
        }

        return null;
    }

    /**
     * Whether the path refers to a package, like "composer://org/repo", rather
     * than to a local file or directory.
     */
    public function isPackage(string $path): bool
    {
        return str_starts_with($path, 'composer://');
    }

    /**
     * Returns the directory of the installed package the path refers to, or
     * null when there is nothing to load: the remote packages are disabled
     * (which is logged), or the path is not a package.
     */
    public function resolvePackage(string $path, ?string $file = null): ?string
    {
        if (!$this->isPackage($path)) {
            return null;
        }

        if ($this->skippedForCompletion) {
            $this->logger->debug(\sprintf('Could not import "%s": Remote packages are not installed during a shell completion.', $path));

            return null;
        }

        if (!$this->isRemoteAllowed()) {
            $this->logger->warning(\sprintf('Could not import "%s": Remote imports are disabled.', $path));

            return null;
        }

        $package = $this->getPackageName($path);

        if (!$packageDirectory = $this->findPackageDirectory($package)) {
            throw new ImportError(\sprintf('The package "%s" is not installed, make sure you required it in your castor.composer.json file.', $package));
        }

        if ($file && !file_exists($packageDirectory . '/' . $file)) {
            throw new ImportError(\sprintf('The file "%s" does not exist in the package "%s".', $file, $package));
        }

        return $packageDirectory;
    }

    /**
     * The name of the package the path refers to, like "org/repo" for
     * "composer://org/repo".
     */
    public function getPackageName(string $path): string
    {
        if (!preg_match('#^composer://(?<package>[^/]+/[^/]+)$#', $path, $matches)) {
            throw new InvalidImportFormat(\sprintf('The path "%s" must be formatted like this: "composer://<organization>/<repository>".', $path));
        }

        return $matches['package'];
    }

    public function clean(): void
    {
        $this->filesystem->remove(PathHelper::getRoot() . '/' . self::VENDOR_DIR);
    }

    /**
     * @param list<string> $args
     * @param bool         $useBundledPackages Whether the packages are loaded in the Castor process, like the
     *                                         remote packages of castor.composer.json are, so they must be
     *                                         resolved against the packages bundled with Castor
     */
    public function run(string $composerJsonFilePath, string $vendorDirectory, array $args, callable|OutputInterface $callback, bool $interactive = false, ?string $binDir = null, bool $useBundledPackages = true): void
    {
        $this->filesystem->mkdir($vendorDirectory);

        if (!$interactive) {
            $args[] = '--no-interaction';
        }

        $self = $_SERVER['PHP_SELF'] ?? '';

        putenv('COMPOSER=' . $composerJsonFilePath);
        $_ENV['COMPOSER'] = $composerJsonFilePath;
        $_SERVER['COMPOSER'] = $composerJsonFilePath;
        putenv('COMPOSER_VENDOR_DIR=' . $vendorDirectory);
        $_ENV['COMPOSER_VENDOR_DIR'] = $vendorDirectory;
        $_SERVER['COMPOSER_VENDOR_DIR'] = $vendorDirectory;
        $_SERVER['PHP_SELF'] = $self . ' composer';

        if ($binDir) {
            putenv('COMPOSER_BIN_DIR=' . $binDir);
            $_ENV['COMPOSER_BIN_DIR'] = $binDir;
            $_SERVER['COMPOSER_BIN_DIR'] = $binDir;
        }

        $useBundledPackages = $useBundledPackages && $this->areBundledPackagesEnabled($composerJsonFilePath);
        $castorPluginLockFile = $this->isCastorPlugin($composerJsonFilePath) ? \dirname($composerJsonFilePath) . '/castor.composer.lock' : null;

        $composerApplication = new ComposerApplication($this->bundledPackages, $useBundledPackages, $castorPluginLockFile);
        $composerApplication->setAutoExit(false);

        $this->logger->debug('Running Composer command.', [
            'args' => implode(' ', $args),
        ]);

        // Composer resolves the relative paths of the file, like the autoload
        // ones, from its working directory: it must be the file's directory,
        // not the one Castor runs from
        $argvInput = new ArgvInput(['composer', '--working-dir=' . \dirname($composerJsonFilePath), ...$args]);
        $bufferedOutput = '';

        $output = $callback instanceof OutputInterface ? $callback : new class($callback, $bufferedOutput) extends Output {
            /** @param callable $callback */
            public function __construct(private $callback, public string &$output)
            {
                parent::__construct();
            }

            public function doWrite(string $message, bool $newline): void
            {
                $this->output .= $message;

                if ($newline) {
                    $this->output .= \PHP_EOL;
                }

                ($this->callback)($message, $newline);
            }
        };

        try {
            $exitCode = $composerApplication->run($argvInput, $output);
        } finally {
            putenv('COMPOSER=');
            putenv('COMPOSER_VENDOR_DIR=');
            $_SERVER['PHP_SELF'] = $self;

            unset($_ENV['COMPOSER'], $_SERVER['COMPOSER'], $_ENV['COMPOSER_VENDOR_DIR'], $_SERVER['COMPOSER_VENDOR_DIR']);
        }

        if ($binDir) {
            putenv('COMPOSER_BIN_DIR=');
            unset($_ENV['COMPOSER_BIN_DIR'], $_SERVER['COMPOSER_BIN_DIR']);
        }

        if (0 !== $exitCode) {
            throw new ComposerError('The Composer process failed: ' . $bufferedOutput . $this->getBundledPackagesHint($exitCode, $useBundledPackages));
        }

        $this->logger->debug('Composer command was successful.', [
            'args' => implode(' ', $args),
            'output' => $bufferedOutput,
        ]);
    }

    private function writeInstalled(string $path, string $composerLockFile): void
    {
        if (!$composerLockContent = @file_get_contents($composerLockFile)) {
            throw new \RuntimeException('The composer.lock file does not exist.');
        }

        $json = json_decode($composerLockContent, true, 512, \JSON_THROW_ON_ERROR);

        file_put_contents("{$path}/composer.installed", json_encode([
            'content-hash' => $json['content-hash'],
            'bundled-packages' => $this->bundledPackages->getHash(),
        ], \JSON_THROW_ON_ERROR));
    }

    private function isInstalled(string $path, string $composerLockFile): bool
    {
        if (!file_exists($composerLockFile)) {
            return false;
        }

        $composerInstalledFile = "{$path}/composer.installed";
        if (!file_exists($composerInstalledFile)) {
            return false;
        }

        if (!$composerLockContent = @file_get_contents($composerLockFile)) {
            throw new \RuntimeException('The composer.lock file does not exist.');
        }

        $hash = json_decode($composerLockContent, true, 512, \JSON_THROW_ON_ERROR)['content-hash'];
        $installed = json_decode((string) file_get_contents($composerInstalledFile), true);

        if (!\is_array($installed)) {
            // Written by a previous Castor version, as the bare content hash
            return false;
        }

        // The remote packages are resolved against the packages bundled with
        // Castor, so they are installed again when these change, like after a
        // Castor update
        return $hash === ($installed['content-hash'] ?? null)
            && $this->bundledPackages->getHash() === ($installed['bundled-packages'] ?? null);
    }

    private function areBundledPackagesEnabled(string $composerJsonFile): bool
    {
        if (!file_exists($composerJsonFile)) {
            return true;
        }

        $json = json_decode((string) file_get_contents($composerJsonFile), true);
        $extra = \is_array($json) ? ($json['extra'] ?? []) : [];

        return BundledPackages::isEnabled(\is_array($extra) ? $extra : []);
    }

    /**
     * The packages of the lock file to update, so they match the packages
     * bundled with Castor.
     *
     * @return list<string>
     */
    private function getOutdatedBundledPackagesInLock(string $composerJsonFile, string $composerLockFile): array
    {
        if (!file_exists($composerLockFile) || !$this->areBundledPackagesEnabled($composerJsonFile)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($composerLockFile), true, 512, \JSON_THROW_ON_ERROR);
        $composerJson = json_decode((string) file_get_contents($composerJsonFile), true, 512, \JSON_THROW_ON_ERROR);

        if (\is_array($composerJson) && $this->isCastorPlugin($composerJsonFile)) {
            // Left out of the remote packages, see ComposerApplication
            unset($composerJson['require-dev']);
        }

        return $this->bundledPackages->findOutdatedInLock(\is_array($lock) ? $lock : [], \is_array($composerJson) ? $composerJson : []);
    }

    /**
     * Whether the file is the composer.json of a Castor plugin, which also
     * lists its remote packages in its own repository.
     */
    private function isCastorPlugin(string $composerJsonFile): bool
    {
        if ('composer.json' !== basename($composerJsonFile) || !file_exists($composerJsonFile)) {
            return false;
        }

        $json = json_decode((string) file_get_contents($composerJsonFile), true);

        return \is_array($json) && self::CASTOR_PLUGIN_TYPE === ($json['type'] ?? null);
    }

    /**
     * The package is looked for in the vendor directory of the root, then in
     * the ones of the mounted directories having their own
     * castor.composer.json.
     */
    private function findPackageDirectory(string $package): ?string
    {
        foreach (array_unique([PathHelper::getCastorVendorDir(), ...$this->installedPackages->getVendorDirectories()]) as $vendorDirectory) {
            if (file_exists($packageDirectory = $vendorDirectory . '/' . $package)) {
                return $packageDirectory;
            }
        }

        return null;
    }

    /**
     * Points to the opt-out when the dependencies cannot be resolved: Composer
     * names the packages bundled with Castor itself, when they are involved.
     */
    private function getBundledPackagesHint(int $exitCode, bool $useBundledPackages): string
    {
        if (!$useBundledPackages || Installer::ERROR_DEPENDENCY_RESOLUTION_FAILED !== $exitCode) {
            return '';
        }

        return \sprintf("\nSet \"extra.castor.%s\" to false in castor.composer.json to ignore the packages bundled with Castor, at the risk of breaking it at runtime (see the documentation about remote imports).", BundledPackages::EXTRA_KEY);
    }
}
