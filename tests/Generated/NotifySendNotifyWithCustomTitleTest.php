<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class NotifySendNotifyWithCustomTitleTest extends TaskTestCase
{
    // notify:send-notify-with-custom-title
    public function test(): void
    {
        $process = $this->runTask(['notify:send-notify-with-custom-title']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
