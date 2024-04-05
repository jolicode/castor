<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class OutputOutputTest extends TaskTestCase
{
    // output:output
    public function test(): void
    {
        $process = $this->runTask(['output:output']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
