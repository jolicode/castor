<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class EventListenerBeforeExecuteTaskTest extends TaskTestCase
{
    // event-listener:before-execute-task
    public function test(): void
    {
        $process = $this->runTask(['event-listener:before-execute-task']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
