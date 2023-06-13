<?php

namespace Castor\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

abstract class TaskTestCase extends TestCase
{
    public function runTask(array $args, string $cwd = null): Process
    {
        $coverage = $this->getTestResultObject()?->getCodeCoverage();

        $bin = __DIR__ . '/../bin/castor';
        $extraEnv = [];

        if ($coverage) {
            $bin = __DIR__ . '/bin/castor';
            $testName = debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'];
            $outputFilename = stream_get_meta_data(tmpfile())['uri'];
            $extraEnv = [
                'CC_OUTPUT_FILENAME' => $outputFilename,
                'CC_TEST_NAME' => $testName,
            ];
        }

        $process = new Process(
            [\PHP_BINARY, $bin, ...$args],
            cwd: $cwd ?? __DIR__ . '/..',
            env: [
                'COLUMNS' => 120,
                ...$extraEnv,
            ],
        );
        $process->run();

        if ($coverage) {
            $coverage->merge(require $outputFilename);
        }

        return $process;
    }

    public static function assertStringEqualsFile(string $expectedFile, string $actualString, string $message = ''): void
    {
        $actualString = OutputCleaner::cleanOutput($actualString);
        parent::assertStringEqualsFile($expectedFile, $actualString, $message);
    }
}
