<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgsAnotherArgsTest extends TaskTestCase
{
    // args:another-args
    public function test(): void
    {
        $process = $this->runTask(['args:another-args', 'FIXME(required)', '--test2', 1]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
