<?php

namespace Castor\Import\Remote;

use Castor\Console\Application;
use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Castor\Import\Exception\ImportError;
use Castor\Import\Exception\InvalidImportFormat;
use Composer\Installer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
class Composer
{
    public const VENDOR_DIR = '.castor/vendor';

    /** Whether the remote packages were needed, but not installed, by a shell completion */
    private bool $skippedForCompletion = false;

    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly Filesystem $filesystem,
        private readonly BundledPackages $bundledPackages,
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

        // Need to look for the raw options as the input is not yet parsed
        if (true !== $this->input->getParameterOption('--no-remote', true)) {
            return false;
        }

        return true;
    }

    public function install(string $entrypointDirectory): void
    {
        $update = true !== $this->input->getParameterOption('--update-remotes', true);
        $displayProgress = 'list' !== $this->input->getFirstArgument() || 'txt' === $this->input->getParameterOption('--format', 'txt');

        if (!file_exists($composerJsonFile = $entrypointDirectory . '/castor.composer.json') && !file_exists($composerJsonFile = $entrypointDirectory . '/.castor/castor.composer.json')) {
            $this->logger->debug(\sprintf('The castor.composer.json file does not exists in %s or %s/.castor, skipping composer install.', $entrypointDirectory, $entrypointDirectory));

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
        if ('_complete' === $this->input->getFirstArgument()) {
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
        $migratedPackages = $update ? [] : $this->getBundledPackagesInLock($composerJsonFile, $composerLockFile);

        if ($migratedPackages) {
            // The lock lists packages now bundled with Castor (it was written
            // by a previous Castor version, or by Composer itself): only these
            // packages are updated, so they leave the lock while the other ones
            // keep their locked version
            $args = ['update', ...$migratedPackages];
        }

        $this->run($composerJsonFile, $vendorDirectory, $args, callback: static function () use ($progressIndicator): void {
            $progressIndicator?->advance();
        });

        $progressIndicator?->finish('<info>Remote packages imported</info>');

        if ($migratedPackages && $displayProgress) {
            $this->output->writeln(\sprintf('<comment>Removed from castor.composer.lock, as bundled with Castor: %s</comment>', implode(', ', $migratedPackages)));
        }

        $this->writeInstalled($vendorDirectory, $composerLockFile);
    }

    public function requireAutoload(): void
    {
        try {
            $autoloadPath = PathHelper::getCastorVendorDir() . '/autoload.php';
        } catch (\RuntimeException $e) {
            return;
        }

        if (!file_exists($autoloadPath)) {
            return;
        }

        require $autoloadPath;
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

        if (!preg_match('#^composer://(?<package>[^/]+/[^/]+)$#', $path, $matches)) {
            throw new InvalidImportFormat(\sprintf('The path "%s" must be formatted like this: "composer://<organization>/<repository>".', $path));
        }

        $package = $matches['package'];
        $packageDirectory = PathHelper::getCastorVendorDir() . '/' . $package;

        if (!file_exists($packageDirectory)) {
            throw new ImportError(\sprintf('The package "%s" is not installed, make sure you required it in your castor.composer.json file.', $package));
        }

        if ($file && !file_exists($packageDirectory . '/' . $file)) {
            throw new ImportError(\sprintf('The file "%s" does not exist in the package "%s".', $file, $package));
        }

        return $packageDirectory;
    }

    public function clean(): void
    {
        $this->filesystem->remove(PathHelper::getRoot() . '/' . self::VENDOR_DIR);
    }

    /**
     * @param list<string> $args
     * @param bool         $replaceBundledPackages Whether the packages are loaded in the Castor process, like the
     *                                             remote packages of castor.composer.json are, so they must be
     *                                             resolved against the packages bundled with Castor
     */
    public function run(string $composerJsonFilePath, string $vendorDirectory, array $args, callable|OutputInterface $callback, bool $interactive = false, ?string $binDir = null, bool $replaceBundledPackages = true): void
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

        $replaceBundledPackages = $replaceBundledPackages && $this->isReplaceBundledPackagesEnabled($composerJsonFilePath);

        $composerApplication = new ComposerApplication($this->bundledPackages, $replaceBundledPackages);
        $composerApplication->setAutoExit(false);

        $this->logger->debug('Running Composer command.', [
            'args' => implode(' ', $args),
        ]);

        $argvInput = new ArgvInput(['composer', ...$args]);
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
            throw new ComposerError('The Composer process failed: ' . $bufferedOutput . $this->getBundledPackagesHint($exitCode, $bufferedOutput, $replaceBundledPackages));
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

    private function isReplaceBundledPackagesEnabled(string $composerJsonFile): bool
    {
        if (!file_exists($composerJsonFile)) {
            return true;
        }

        $json = json_decode((string) file_get_contents($composerJsonFile), true);
        $extra = \is_array($json) ? ($json['extra'] ?? []) : [];

        return BundledPackages::isReplaceEnabled(\is_array($extra) ? $extra : []);
    }

    /**
     * The packages listed in the lock file that are bundled with Castor.
     *
     * @return list<string>
     */
    private function getBundledPackagesInLock(string $composerJsonFile, string $composerLockFile): array
    {
        if (!file_exists($composerLockFile) || !$this->isReplaceBundledPackagesEnabled($composerJsonFile)) {
            return [];
        }

        $lock = json_decode((string) file_get_contents($composerLockFile), true, 512, \JSON_THROW_ON_ERROR);
        $names = [];

        foreach (['packages', 'packages-dev'] as $key) {
            foreach (\is_array($lock) && \is_array($lock[$key] ?? null) ? $lock[$key] : [] as $package) {
                if (\is_array($package) && \is_string($package['name'] ?? null)) {
                    $names[] = $package['name'];
                }
            }
        }

        return array_values(array_intersect($names, array_keys($this->bundledPackages->getReplaces())));
    }

    /**
     * Explains a dependency resolution failure that may come from the packages
     * bundled with Castor, which the remote packages are resolved against.
     */
    private function getBundledPackagesHint(int $exitCode, string $output, bool $replaceBundledPackages): string
    {
        if (!$replaceBundledPackages || Installer::ERROR_DEPENDENCY_RESOLUTION_FAILED !== $exitCode) {
            return '';
        }

        $involved = [];

        foreach ($this->bundledPackages->getReplaces() as $name => $version) {
            if (preg_match('{' . preg_quote($name, '{') . '(?![\w.-])}', $output)) {
                $involved[] = "{$name} ({$version})";
            }
        }

        return \sprintf(
            "\nThe remote packages are loaded in the same PHP process as Castor %s, which already provides %s: the packages required in castor.composer.json must be compatible with these versions. Run \"castor composer show --self\" to list the packages Castor provides, or set \"extra.castor.%s\" to false in castor.composer.json to install your own copies anyway, at the risk of breaking Castor at runtime.",
            Application::VERSION,
            $involved ? implode(', ', $involved) : 'some packages',
            BundledPackages::EXTRA_KEY,
        );
    }
}
