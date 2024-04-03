<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class RunWithProcessHelperTest extends TaskTestCase
{
    // run:with-process-helper
    public function test(): void
    {
        $process = $this->runTask(['run:with-process-helper']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
