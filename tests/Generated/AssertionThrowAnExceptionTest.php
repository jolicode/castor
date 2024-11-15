<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AssertionThrowAnExceptionTest extends TaskTestCase
{
    // assertion:throw-an-exception
    public function test(): void
    {
        $process = $this->runTask(['assertion:throw-an-exception']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
