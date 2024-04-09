<?php

namespace Castor\Import\Remote;

use Castor\Console\Application;
use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** @internal */
class Composer
{
    public const VENDOR_DIR = '/.castor/vendor/';
    public const DEFAULT_COMPOSER_CONFIGURATION = [
        'description' => 'This file is managed by Castor. Do not edit it manually.',
        'config' => [
            'sort-packages' => true,
        ],
        'replace' => [
            'castor/castor' => Application::VERSION,
        ],
    ];

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly OutputInterface $output,
        /** @var array<string, mixed> */
        private array $configuration = self::DEFAULT_COMPOSER_CONFIGURATION,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function update(bool $force = false, bool $displayProgress = true): void
    {
        $composer = (new ExecutableFinder())->find('composer');

        if (!$composer) {
            throw new ComposerError('The "composer" executable was not found. In order to use remote import, please make sure that Composer is installed and available in your PATH.');
        }

        $dir = PathHelper::getRoot() . self::VENDOR_DIR;

        $this->filesystem->mkdir($dir);

        file_put_contents($dir . '.gitignore', "*\n");

        $this->writeJsonFile($dir);

        $ran = false;

        if ($force || !$this->isInstalled($dir)) {
            $progressIndicator = null;
            if ($displayProgress) {
                $progressIndicator = new ProgressIndicator($this->output, null, 100, ['⠏', '⠛', '⠹', '⢸', '⣰', '⣤', '⣆', '⡇']);
                $progressIndicator->start('<comment>Downloading remote packages</comment>');
            }

            $this->run(['update'], callback: function () use ($progressIndicator) {
                if ($progressIndicator) {
                    $progressIndicator->advance();
                }
            });

            if ($progressIndicator) {
                $progressIndicator->finish('<info>Remote packages imported</info>');
            }
            $this->writeInstalled($dir);

            $ran = true;
        }

        if (!$ran) {
            $this->logger->debug('Packages were already required, no need to run Composer.');
        }
    }

    public function remove(): void
    {
        $this->filesystem->remove(PathHelper::getRoot() . self::VENDOR_DIR);
    }

    /**
     * @param string[] $args
     */
    private function run(array $args, callable $callback): void
    {
        $this->logger->debug('Running Composer command.', [
            'args' => implode(' ', $args),
        ]);

        $dir = PathHelper::getRoot() . self::VENDOR_DIR;

        $process = new Process(['composer', ...$args, '--working-dir', $dir]);
        $process->setEnv([
            'COMPOSER_VENDOR_DIR' => $dir,
        ]);
        $process->run($callback);

        if (!$process->isSuccessful()) {
            throw new ComposerError('The Composer process failed: ' . $process->getErrorOutput());
        }

        $this->logger->debug('Composer command was successful.', [
            'args' => implode(' ', $args),
            'output' => $process->getOutput(),
        ]);
    }

    private function writeJsonFile(string $path): void
    {
        file_put_contents("{$path}/composer.json", json_encode($this->configuration, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));
    }

    private function writeInstalled(string $path): void
    {
        file_put_contents("{$path}/composer.installed", hash('sha256', json_encode($this->configuration, \JSON_THROW_ON_ERROR)));
    }

    private function isInstalled(string $path): bool
    {
        $path = "{$path}/composer.installed";

        return file_exists($path) && file_get_contents($path) === hash('sha256', json_encode($this->configuration, \JSON_THROW_ON_ERROR));
    }
}
