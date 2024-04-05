<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ContextContextWithTest extends TaskTestCase
{
    // context:context-with
    public function test(): void
    {
        $process = $this->runTask(['context:context-with']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
