<?php

namespace Castor\Helper;

use Castor\Console\Application;
use Castor\Console\Output\SectionOutput;
use Castor\Context;
use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\Process\Process;

use function Castor\run;

/** @internal */
final class WatchHelper
{
    /**
     * @param string|non-empty-array<string>                 $path
     * @param (callable(string, string) : (false|void|null)) $function
     */
    public static function watch(Application $app, SectionOutput $sectionOutput, string|array $path, callable $function, Context $context): void
    {
        $output = $sectionOutput->getConsoleOutput();

        if (\is_array($path)) {
            $parallelCallbacks = [];

            foreach ($path as $p) {
                $parallelCallbacks[] = fn () => self::watch($app, $sectionOutput, $p, $function, $context);
            }

            ParallelHelper::parallel($app, $output, ...$parallelCallbacks);

            return;
        }

        $binary = match (true) {
            OsHelper::isMacOS() => match (php_uname('m')) {
                'arm64' => 'watcher-darwin-arm64',
                default => 'watcher-darwin-amd64',
            },
            OsHelper::isWindows() => 'watcher-windows.exe',
            default => match (php_uname('m')) {
                'arm64' => 'watcher-linux-arm64',
                default => 'watcher-linux-amd64',
            },
        };

        $binaryPath = __DIR__ . '/../../tools/watcher/bin/' . $binary;

        if (str_starts_with(__FILE__, 'phar:')) {
            static $tmpPath;

            if (null === $tmpPath) {
                $tmpPath = sys_get_temp_dir() . '/' . $binary;
                copy($binaryPath, $tmpPath);
                chmod($tmpPath, 0o755);
            }

            $binaryPath = $tmpPath;
        }

        $watchContext = $context->withTty(false)->withPty(false)->withTimeout(null);

        $command = [$binaryPath, $path];
        $buffer = '';

        run($command, callback: static function ($type, $bytes, $process) use ($function, $sectionOutput, &$buffer) {
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
                $sectionOutput->writeProcessOutput($type, $bytes, $process);
            }
        }, context: $watchContext);
    }
}
