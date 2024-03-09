<?php

namespace Castor\Tests;

use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\Helper\WebServerHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

abstract class TaskTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        WebServerHelper::start();
    }

    public function runTask(array $args, ?string $cwd = null): Process
    {
        $coverage = $this->getTestResultObject()?->getCodeCoverage();

        $castorBin = $_SERVER['CASTOR_BIN'] ?? __DIR__ . '/../bin/castor';

        $extraEnv = [
            'ENDPOINT' => $_SERVER['ENDPOINT'],
        ];

        if ($coverage) {
            $castorBin = __DIR__ . '/bin/castor';
            $testName = debug_backtrace()[1]['class'] . '::' . debug_backtrace()[1]['function'];
            $outputFilename = stream_get_meta_data(tmpfile())['uri'];
            $extraEnv = [
                'CC_OUTPUT_FILENAME' => $outputFilename,
                'CC_TEST_NAME' => $testName,
            ];
        }

        $process = new Process(
            [$castorBin, '--no-ansi', ...$args],
            cwd: $cwd ? str_replace('{{ base }}', __DIR__ . '/..', $cwd) : __DIR__ . '/..',
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
