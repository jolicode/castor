<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class WaitForWaitForDockerContainerTaskTest extends TaskTestCase
{
    // wait-for:wait-for-docker-container-task
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-docker-container-task']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
