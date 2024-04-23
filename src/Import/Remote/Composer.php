<?php

namespace Castor\Import\Remote;

use Castor\Helper\PathHelper;
use Castor\Import\Exception\ComposerError;
use Composer\Console\Application as ComposerApplication;
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
        private readonly Filesystem $filesystem,
        private readonly InputInterface $input,
        private readonly OutputInterface $output,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function install(string $entrypointDirectory): void
    {
        $update = true !== $this->input->getParameterOption('--update-remotes', true);
        $displayProgress = 'list' !== $this->input->getFirstArgument();

        if (!file_exists($file = $entrypointDirectory . '/castor.composer.json') && !file_exists($file = $entrypointDirectory . '/.castor/castor.composer.json')) {
            $this->logger->debug(sprintf('The castor.composer.json file does not exists in %s or %s/.castor, skipping composer install.', $entrypointDirectory, $entrypointDirectory));

            return;
        }

        $vendorDirectory = $entrypointDirectory . '/' . self::VENDOR_DIR;

        if (!$update && $this->isInstalled($vendorDirectory, $file)) {
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

        $this->run($file, $vendorDirectory, [$command], callback: function () use ($progressIndicator) {
            if ($progressIndicator) {
                $progressIndicator->advance();
            }
        });

        if ($progressIndicator) {
            $progressIndicator->finish('<info>Remote packages imported</info>');
        }

        $this->writeInstalled($vendorDirectory, $file);
    }

    public function remove(): void
    {
        $this->filesystem->remove(PathHelper::getRoot() . '/' . self::VENDOR_DIR);
    }

    /**
     * @param string[] $args
     */
    public function run(string $composerJsonFilePath, string $vendorDirectory, array $args, callable|OutputInterface $callback, bool $interactive = false): void
    {
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

    private function writeInstalled(string $path, string $composerFilePath): void
    {
        file_put_contents("{$path}/composer.installed", hash('sha256', json_encode(file_get_contents($composerFilePath), \JSON_THROW_ON_ERROR)));
    }

    private function isInstalled(string $path, string $composerFilePath): bool
    {
        $path = "{$path}/composer.installed";

        return file_exists($path) && file_get_contents($path) === hash('sha256', json_encode(file_get_contents($composerFilePath), \JSON_THROW_ON_ERROR));
    }
}
