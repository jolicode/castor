<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsSimpleTest extends TaskTestCase
{
    // arguments:simple
    public function test(): void
    {
        $process = $this->runTask(['arguments:simple', 'FIXME(first-arg)', 'FIXME(second-arg)']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
