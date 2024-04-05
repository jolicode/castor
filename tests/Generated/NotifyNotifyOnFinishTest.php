<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NotifyNotifyOnFinishTest extends TaskTestCase
{
    // notify:notify-on-finish
    public function test(): void
    {
        $process = $this->runTask(['notify:notify-on-finish']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
