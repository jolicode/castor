<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ArgPassthruExpandedTest extends TaskTestCase
{
    // args:passthru
    public function test(): void
    {
        $process = $this->runTask(['args:passthru', 'a', 'b', '--no', '--foo', 'bar', '-x']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
