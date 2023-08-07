<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class FingerprintComplexTaskTest extends TaskTestCase
{
    // fingerprint:complex-task
    public function test(): void
    {
        $this->clearTestsArtifacts();

        // Run for the first time, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt');

        // should don't run because the fingerprint is the same
        $this->runProcessAndExpect(__FILE__ . '.output_not_runnable.txt');

        // nothing should happen because the fingerprint is the same for complex, but not for simple that is called inside complex
        $this->runProcessAndExpect(__FILE__ . '.output_not_runnable.txt', 'Hello World', 'Hello');

        // change file content for complex, should run
        $this->runProcessAndExpect(__FILE__ . '.output_runnable.txt', 'Hello World', 'Hello World');

        $this->clearTestsArtifacts();
    }

    private function runProcessAndExpect(
        string $expectedOutputFilePath,
        string $withFileContentForSimple = 'Hello',
        string $withFileContentForComplex = 'Hello'
    ): void {
        $simpleFilepath = \dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_single';
        if (file_exists($simpleFilepath)) {
            unlink($simpleFilepath);
        }

        file_put_contents($simpleFilepath, $withFileContentForSimple);

        $complexFilepath = \dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_multiple';
        if (file_exists($complexFilepath)) {
            unlink($complexFilepath);
        }

        file_put_contents($complexFilepath, $withFileContentForComplex);

        $process = $this->runTask(['fingerprint:complex-task']);

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
        if (file_exists(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_multiple')) {
            unlink(\dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_multiple');
        }
    }
}
