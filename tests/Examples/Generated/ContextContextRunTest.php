<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ContextContextRunTest extends TaskTestCase
{
    // context:context
    public function test(): void
    {
        $process = $this->runTask(['context:context', '--context', 'run']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
