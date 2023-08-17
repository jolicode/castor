<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class NotifyNotifyOnFinishTest extends TaskTestCase
{
    // notify:notify-on-finish
    public function test(): void
    {
        $process = $this->runTask(['notify:notify-on-finish', '--force']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
