<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class HelloTest extends TaskTestCase
{
    // hello
    public function test(): void
    {
        $process = $this->runTask(['hello']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
