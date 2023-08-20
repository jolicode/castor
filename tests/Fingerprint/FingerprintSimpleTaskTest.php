<?php

namespace Castor\Tests\Fingerprint;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Finder\Finder;

class FingerprintSimpleTaskTest extends TaskTestCase
{
    use FingerprintedTest;

    // fingerprint:in-method
    public function test(): void
    {
        // Run for the first time, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt');

        // should don't run because the fingerprint is the same
        $this->runProcessAndExpect(__FILE__ . '.output_not_runnable.txt');

        // change file content, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt', 'Hello World');
    }

    private function runProcessAndExpect(string $expectedOutputFilePath, string $withFileContent = 'Hello'): void
    {
        // remove all contents of "/tmp/castor" directory
        $filepath = \dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_single';
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        file_put_contents($filepath, $withFileContent);

        $process = $this->runTask(['fingerprint:simple-task']);

        if (file_exists($expectedOutputFilePath)) {
            $this->assertStringEqualsFile($expectedOutputFilePath, $process->getOutput());
        }

        $this->assertSame(0, $process->getExitCode());
    }
}
