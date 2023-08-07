<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class FingerprintInMethodTest extends TaskTestCase
{
    // fingerprint:simple-task
    public function test(): void
    {
        $this->clearTestsArtifacts();

        // Run for the first time, should run
        $this->runProcessAndExpect(__FILE__ . '.output_with_sub_task.txt');

        // should don't run because the fingerprint is the same
        $this->runProcessAndExpect(__FILE__ . '.output_without_sub_task.txt');

        // change file content, should run
        $this->runProcessAndExpect(__FILE__ . '.output_with_sub_task.txt', 'Hello World');

        $this->clearTestsArtifacts();
    }

    private function runProcessAndExpect(string $expectedOutputFilePath, string $withFileContent = 'Hello'): void
    {
        $filepath = \dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_in_method';
        if (file_exists($filepath)) {
            unlink($filepath);
        }

        file_put_contents($filepath, $withFileContent);

        $process = $this->runTask(['fingerprint:in-method']);

        if (file_exists($expectedOutputFilePath)) {
            $this->assertStringEqualsFile($expectedOutputFilePath, $process->getOutput());
        }

        $this->assertSame(0, $process->getExitCode());
    }

    private function clearTestsArtifacts(): void
    {
        shell_exec('rm -rf /tmp/castor');
        if (file_exists(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_in_method')) {
            unlink(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_in_method');
        }
    }
}
