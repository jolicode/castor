<?php

namespace Castor\Tests\Helper;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class WebServerHelper
{
    private static ?Process $process = null;

    public static function start(): void
    {
        if (self::$process) {
            return;
        }

        self::$process = new Process(
            [
                \PHP_BINARY,
                '-S', str_replace('http://', '', $_SERVER['ENDPOINT']),
            ],
            cwd: __DIR__ . '/fixtures/http',
        );
        self::$process->start();
        usleep(100_000);
        if (!self::$process->isRunning()) {
            throw new ProcessFailedException(self::$process);
        }

        register_shutdown_function(fn () => self::stop());
    }

    private static function stop(): void
    {
        if (self::$process) {
            self::$process->stop();
        }

        self::$process = null;
    }
}
