<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgPassthruExpandedTest extends TaskTestCase
{
    // args:passthru
    public function test(): void
    {
        $process = $this->runTask(['args:passthru', 'a', 'b', '--no', '--foo', 'bar', '-x']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
