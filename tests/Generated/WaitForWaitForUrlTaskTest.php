<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class WaitForWaitForUrlTaskTest extends TaskTestCase
{
    // wait-for:wait-for-url-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-task']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
