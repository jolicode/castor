<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class NewProjectInitTest extends TaskTestCase
{
    // init
    public function test(): void
    {
        $process = $this->runTask(['init'], '/tmp');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
