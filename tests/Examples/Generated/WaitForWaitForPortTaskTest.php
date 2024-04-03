<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class WaitForWaitForPortTaskTest extends TaskTestCase
{
    // wait-for:wait-for-port-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-port-task']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
