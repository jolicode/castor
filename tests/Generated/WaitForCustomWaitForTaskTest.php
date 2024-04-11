<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WaitForCustomWaitForTaskTest extends TaskTestCase
{
    // wait-for:custom-wait-for-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:custom-wait-for-task', '--thing', 'foobar']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
