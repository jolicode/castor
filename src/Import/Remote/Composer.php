<?php

namespace Castor\Import\Remote;

use Castor\Console\Application;
use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Composer\Console\Application as ComposerApplication;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/** @internal */
class Composer
{
    public const VENDOR_DIR = '/.castor/vendor/';
    public const DEFAULT_COMPOSER_CONFIGURATION = [
        'description' => 'This file is managed by Castor. Do not edit it manually.',
        'config' => [
            'sort-packages' => true,
            'vendor-dir' => '.',
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
        $dir = PathHelper::getRoot() . self::VENDOR_DIR;

        if ($force || !$this->isInstalled($dir)) {
            $this->filesystem->mkdir($dir);

            file_put_contents($dir . '.gitignore', "*\n");
            file_put_contents("{$dir}/composer.json", json_encode($this->configuration, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR));

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
        } else {
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
        $directory = PathHelper::getRoot() . self::VENDOR_DIR;

        $args[] = '--working-dir';
        $args[] = $directory;

        $composerApplication = new ComposerApplication();
        $composerApplication->setAutoExit(false);

        $this->logger->debug('Running Composer command.', [
            'args' => implode(' ', $args),
        ]);

        $argvInput = new ArgvInput(['composer', ...$args]);

        $output = new class($callback) extends Output {
            /** @param callable $callback */
            public function __construct(private $callback, public string $output = '')
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

        if (0 !== $exitCode) {
            throw new ComposerError('The Composer process failed: ' . $output->output);
        }

        $this->logger->debug('Composer command was successful.', [
            'args' => implode(' ', $args),
            'output' => $output->output,
        ]);
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
