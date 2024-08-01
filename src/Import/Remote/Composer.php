<?php

namespace Castor\Import\Remote;

use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Castor\Import\Exception\ImportError;
use Castor\Import\Exception\InvalidImportFormat;
use Castor\Import\Exception\RemoteNotAllowed;
use Castor\Import\Mount;
use Castor\Kernel;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
class Composer
{
    public const VENDOR_DIR = '.castor/vendor';

    public function __construct(
        private readonly Kernel $kernel,
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function isRemoteAllowed(): bool
    {
        if ($_SERVER['CASTOR_NO_REMOTE'] ?? false) {
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

        $this->filesystem->mkdir($vendorDirectory);
        $this->filesystem->dumpFile($vendorDirectory . '/.gitignore', "*\n");

        $progressIndicator = null;

        if ($displayProgress) {
            $progressIndicator = new ProgressIndicator($this->output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
            $progressIndicator->start('<comment>Downloading remote packages</comment>');
        }

        $command = $update ? 'update' : 'install';

        $this->run($composerJsonFile, $vendorDirectory, [$command], callback: function () use ($progressIndicator) {
            if ($progressIndicator) {
                $progressIndicator->advance();
            }
        });

        if ($progressIndicator) {
            $progressIndicator->finish('<info>Remote packages imported</info>');
        }

        $this->writeInstalled($vendorDirectory, $composerLockFile);
    }

    public function requireAutoload(): void
    {
        $autoloadPath = PathHelper::getCastorVendorDir() . '/autoload.php';

        if (!file_exists($autoloadPath)) {
            return;
        }

        require $autoloadPath;
    }

    public function importFromPackage(string $scheme, string $package, ?string $file = null): void
    {
        if (!$this->isRemoteAllowed()) {
            throw new RemoteNotAllowed('Remote imports are disabled.');
        }

        if (!preg_match('#^(?<organization>[^/]+)/(?<repository>[^/]+)$#', $package)) {
            throw new InvalidImportFormat(\sprintf('The import path must be formatted like this: "%s://<organization>/<repository>".', $scheme));
        }

        if ('composer' === $scheme || 'package' === $scheme) {
            if ('package' === $scheme) {
                @trigger_deprecation('castor/castor', '0.16.0', 'The "package" scheme is deprecated, use "composer" instead.');
            }

            $packageDirectory = PathHelper::getCastorVendorDir() . '/' . $package;

            if (!file_exists($packageDirectory)) {
                throw new ImportError(\sprintf('The package "%s" is not installed, make sure you required it in your castor.composer.json file.', $package));
            }

            if ($file && !file_exists($packageDirectory . '/' . $file)) {
                throw new ImportError(\sprintf('The file "%s" does not exist in the package "%s".', $file, $package));
            }

            $this->kernel->addMount(new Mount(
                PathHelper::getCastorVendorDir() . '/' . $package,
                allowEmptyEntrypoint: true,
                allowRemotePackage: false,
                file: $file,
            ));

            return;
        }

        throw new InvalidImportFormat(\sprintf('The import scheme "%s" is not supported.', $scheme));
    }

    public function clean(): void
    {
        $this->filesystem->remove(PathHelper::getRoot() . '/' . self::VENDOR_DIR);
    }

    /**
     * @param string[] $args
     */
    public function run(string $composerJsonFilePath, string $vendorDirectory, array $args, callable|OutputInterface $callback, bool $interactive = false): void
    {
        $this->filesystem->mkdir($vendorDirectory);

        $args[] = '--working-dir';
        $args[] = \dirname($vendorDirectory);

        if (!$interactive) {
            $args[] = '--no-interaction';
        }

        putenv('COMPOSER=' . $composerJsonFilePath);
        $_ENV['COMPOSER'] = $composerJsonFilePath;
        $_SERVER['COMPOSER'] = $composerJsonFilePath;

        $composerApplication = new ComposerApplication();
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

        $exitCode = $composerApplication->run($argvInput, $output);

        putenv('COMPOSER=');
        unset($_ENV['COMPOSER'], $_SERVER['COMPOSER']);

        if (0 !== $exitCode) {
            throw new ComposerError('The Composer process failed: ' . $bufferedOutput);
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

        file_put_contents("{$path}/composer.installed", $json['content-hash']);
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

        return $hash === file_get_contents($composerInstalledFile);
    }
}
