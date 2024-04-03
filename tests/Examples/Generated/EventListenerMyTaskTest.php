<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class EventListenerMyTaskTest extends TaskTestCase
{
    // event-listener:my-task
    public function test(): void
    {
        $process = $this->runTask(['event-listener:my-task']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
