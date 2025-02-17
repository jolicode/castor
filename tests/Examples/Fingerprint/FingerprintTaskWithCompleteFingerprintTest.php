<?php

namespace Castor\Tests\Examples\Fingerprint;

class FingerprintTaskWithCompleteFingerprintTest extends FingerprintedTestCase
{
    // fingerprint:task-with-some-fingerprint
    public function test(): void
    {
        // Run for the first time, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt');

        // should not run because the fingerprint is the same
        $this->runProcessAndExpect(__FILE__ . '.output_not_runnable.txt');

        // change file content, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt', 'Hello World');
    }

    private function runProcessAndExpect(string $expectedOutputFilePath, string $withFileContent = 'Hello'): void
    {
        $filepath = \dirname(__DIR__, 3) . '/examples/fingerprint_file.fingerprint_single';
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        file_put_contents($filepath, $withFileContent);

        $process = $this->runTask(['fingerprint:task-with-complete-fingerprint'], needResetCache: false);

        if (file_exists($expectedOutputFilePath)) {
            $this->assertStringEqualsFile($expectedOutputFilePath, $process->getOutput());
        }

        $this->assertSame(0, $process->getExitCode());
    }
}
