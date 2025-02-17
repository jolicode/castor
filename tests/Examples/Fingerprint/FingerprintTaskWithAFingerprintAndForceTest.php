<?php

namespace Castor\Tests\Examples\Fingerprint;

class FingerprintTaskWithAFingerprintAndForceTest extends FingerprintedTestCase
{
    // fingerprint:task-with-a-fingerprint-and-force
    public function test(): void
    {
        $filepath = \dirname(__DIR__, 3) . '/examples/fingerprint_file.fingerprint_single';

        if (file_exists($filepath)) {
            unlink($filepath);
        }

        file_put_contents($filepath, 'Hello');

        $processFirstRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force'], needResetCache: false);
        $processSecondRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force', '--force'], needResetCache: false);
        $processThirdRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force'], needResetCache: false);

        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processFirstRun->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processSecondRun->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.output_not_runnable.txt', $processThirdRun->getOutput());

        file_put_contents($filepath, 'Hello World');
        // If we don't force, it should re-run the task
        $processFourthRun = $this->runTask(['fingerprint:task-with-a-fingerprint-and-force'], needResetCache: false);
        $this->assertStringEqualsFile(__FILE__ . '.output_runnable.txt', $processFourthRun->getOutput());

        foreach ([$processFirstRun, $processSecondRun, $processThirdRun, $processFourthRun] as $process) {
            $this->assertSame(0, $process->getExitCode());
        }
    }
}
