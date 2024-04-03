<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class QuietQuietTest extends TaskTestCase
{
    // quiet:quiet
    public function test(): void
    {
        $process = $this->runTask(['quiet:quiet']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
