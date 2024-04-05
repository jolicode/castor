<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NoConfigUnknownTest extends TaskTestCase
{
    // unknown:task
    public function test(): void
    {
        $process = $this->runTask(['unknown:task'], '/tmp');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
