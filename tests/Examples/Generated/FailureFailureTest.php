<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class FailureFailureTest extends TaskTestCase
{
    // failure:failure
    public function test(): void
    {
        $process = $this->runTask(['failure:failure']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
