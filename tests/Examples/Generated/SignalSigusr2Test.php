<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class SignalSigusr2Test extends TaskTestCase
{
    // signal:sigusr2
    public function test(): void
    {
        $process = $this->runTask(['signal:sigusr2']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
