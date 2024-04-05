<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NoConfigUnknownWithArgsTest extends TaskTestCase
{
    // unknown:task
    public function test(): void
    {
        $process = $this->runTask(['unknown:task', 'toto', '--foo', 1], '/tmp');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
