<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class RunVariablesTest extends TaskTestCase
{
    // run:variables
    public function test(): void
    {
        $process = $this->runTask(['run:variables']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
