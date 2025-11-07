<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FingerprintExistAndSaveTest extends TaskTestCase
{
    // fingerprint:exist-and-save
    public function test(): void
    {
        $process = $this->runTask(['fingerprint:exist-and-save']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
