<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class BarBarTest extends TaskTestCase
{
    // bar:bar
    public function test(): void
    {
        $process = $this->runTask(['bar:bar']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
