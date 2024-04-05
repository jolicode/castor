<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ParallelExceptionTest extends TaskTestCase
{
    // parallel:exception
    public function test(): void
    {
        $process = $this->runTask(['parallel:exception']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
