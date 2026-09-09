<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SignalTrapWithOnSignalsTest extends TaskTestCase
{
    // signal:trap-with-on-signals
    public function test(): void
    {
        $process = $this->runTask(['signal:trap-with-on-signals']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
