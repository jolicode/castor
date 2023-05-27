<?php

namespace Castor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

abstract class TaskTestCase extends TestCase
{
    public function runTask(array $args): Process
    {
        $process = new Process(
            [\PHP_BINARY, 'bin/castor', ...$args],
            cwd: __DIR__ . '/..',
            env: [
                'COLUMNS' => 120,
            ],
        );
        $process->run();

        return $process;
    }

    public static function assertStringEqualsFile(string $expectedFile, string $actualString, string $message = ''): void
    {
        $actualString = OutputCleaner::cleanOutput($actualString);
        parent::assertStringEqualsFile($expectedFile, $actualString, $message);
    }
}
