<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ArgsAnotherArgsTest extends TaskTestCase
{
    // args:another-args
    public function test(): void
    {
        $process = $this->runTask(['args:another-args', 'FIXME(required)', '--test2', 1]);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
