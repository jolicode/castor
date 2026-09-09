<?php

namespace Castor\Runner;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Castor\Context;
use Castor\ContextRegistry;
use Castor\Helper\Architecture;
use Castor\Helper\Installation;
use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/** @internal */
final readonly class WatchRunner
{
    public function __construct(
        private ContextRegistry $contextRegistry,
        private ParallelRunner $parallelRunner,
        private ProcessRunner $processRunner,
        private SectionOutput $sectionOutput,
        private Installation $installation,
        private Filesystem $filesystem,
        private string $cacheDir,
    ) {
    }

    /**
     * @param string|non-empty-array<string>                 $path
     * @param (callable(string, string) : (false|void|null)) $function
     */
    public function watch(string|array $path, callable $function, ?Context $context = null): void
    {
        $context ??= $this->contextRegistry->getCurrentContext();

        if (\is_array($path)) {
            $parallelCallbacks = [];

            foreach ($path as $p) {
                $parallelCallbacks[] = fn () => $this->watch($p, $function, $context);
            }

            $this->parallelRunner->parallel(...$parallelCallbacks);

            return;
        }

        $architecture = $this->installation->getArchitecture();

        $binary = match (true) {
            OsHelper::isMacOS() => match ($architecture) {
                Architecture::Arm64 => 'watcher-darwin-arm64',
                Architecture::Amd64 => 'watcher-darwin-amd64',
            },
            OsHelper::isWindows() => 'watcher-windows.exe',
            default => match ($architecture) {
                Architecture::Arm64 => 'watcher-linux-arm64',
                Architecture::Amd64 => 'watcher-linux-amd64',
            },
        };

        $binaryPath = __DIR__ . '/../../tools/watcher/bin/' . $binary;

        if (str_starts_with(__FILE__, 'phar:')) {
            $binaryPath = $this->extractBinary($binaryPath, $binary);
        }

        $watchContext = $context->withTty(false)->withPty(false)->withTimeout(null);

        $command = [$binaryPath, $path];
        $buffer = '';

        $this->processRunner->run($command, context: $watchContext, callback: function ($type, $bytes, $process) use ($function, &$buffer): void {
            if (Process::OUT === $type) {
                $data = $buffer . $bytes;
                $lines = explode("\n", $data);

                while (!empty($lines)) {
                    $line = trim($lines[0]);

                    if ('' === $line) {
                        array_shift($lines);

                        continue;
                    }

                    try {
                        $eventLine = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
                    } catch (\JsonException) {
                        $buffer = implode("\n", $lines);

                        break;
                    }

                    $result = $function($eventLine['name'], $eventLine['operation']);

                    if (false === $result) {
                        $process->stop();
                    }

                    array_shift($lines);
                }
            } else {
                $this->sectionOutput->writeProcessOutput($type, $bytes, $process);
            }
        });
    }

    /**
     * A binary cannot be executed from inside a phar: the watcher is extracted
     * to the user cache directory, under the Castor version, and reused on the
     * next runs as long as its content is the expected one.
     */
    private function extractBinary(string $binaryPath, string $binary): string
    {
        $target = $this->cacheDir . '/watcher/' . Application::VERSION . '/' . $binary;

        if (!is_file($target) || hash_file('sha256', $target) !== hash_file('sha256', $binaryPath)) {
            $this->filesystem->mkdir(\dirname($target), 0o700);
            $this->filesystem->copy($binaryPath, $target, true);
            $this->filesystem->chmod($target, 0o755);
        }

        return $target;
    }
}
