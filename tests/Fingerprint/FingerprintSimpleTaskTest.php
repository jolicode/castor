<?php

namespace Castor\Tests\Fingerprint;

use Castor\Tests\TaskTestCase;

class FingerprintSimpleTaskTest extends TaskTestCase
{
    // fingerprint:in-method
    public function test(): void
    {
        $this->clearTestsArtifacts();

        // Run for the first time, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt');

        // should don't run because the fingerprint is the same
        $this->runProcessAndExpect(__FILE__ . '.output_not_runnable.txt');

        // change file content, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt', 'Hello World');

        $this->clearTestsArtifacts();
    }

    private function runProcessAndExpect(string $expectedOutputFilePath, string $withFileContent = 'Hello'): void
    {
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

    private function clearTestsArtifacts(): void
    {
        shell_exec('rm -rf /tmp/castor');
        if (file_exists(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_single')) {
            unlink(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_single');
        }
    }
}
