<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NotifySendNotifyTest extends TaskTestCase
{
    // notify:send-notify
    public function test(): void
    {
        $process = $this->runTask(['notify:send-notify']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
