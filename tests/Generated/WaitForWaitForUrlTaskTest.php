<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WaitForWaitForUrlTaskTest extends TaskTestCase
{
    // wait-for:wait-for-url-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-task']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
