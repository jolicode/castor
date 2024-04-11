<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class NoConfigUnknownWithArgsTest extends TaskTestCase
{
    // unknown:task
    public function test(): void
    {
        $process = $this->runTask(['unknown:task', 'toto', '--foo', 1], '/tmp');

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
