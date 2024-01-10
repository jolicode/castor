<?php

namespace Castor\Tests\Fingerprint;

use Castor\Tests\TaskTestCase;

class FingerprintTaskWithAFingerprintAndForceTest extends TaskTestCase
{
    use FingerprintedTest;

    // fingerprint:task-with-a-fingerprint-and-force
    public function test(): void
    {
        $filepath = \dirname(__DIR__, 2) . '/examples/fingerprint_file.fingerprint_single';

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        file_put_contents($filepath, 'Hello');

        $processFirstRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force']);
        $processSecondRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force', '--force']);
        $processThirdRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force']);

        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processFirstRun->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processSecondRun->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.output_not_runnable.txt', $processThirdRun->getOutput());

        file_put_contents($filepath, 'Hello World');
        // If we don't force, it should re-run the task
        $processFourthRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force']);
        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processFourthRun->getOutput());

        foreach ([$processFirstRun, $processSecondRun, $processThirdRun, $processFourthRun] as $process) {
            $this->assertSame(0, $process->getExitCode());
        }
    }
}
