<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class RunTestFileTest extends TaskTestCase
{
    // run:test-file
    public function test(): void
    {
        $process = $this->runTask(['run:test-file']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
