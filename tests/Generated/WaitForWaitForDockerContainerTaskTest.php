<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class WaitForWaitForDockerContainerTaskTest extends TaskTestCase
{
    // wait-for:wait-for-docker-container-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-docker-container-task']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
