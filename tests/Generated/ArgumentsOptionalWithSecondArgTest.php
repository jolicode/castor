<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsOptionalWithSecondArgTest extends TaskTestCase
{
    // arguments:optional
    public function test(): void
    {
        $process = $this->runTask(['arguments:optional', 'foo', '--second-arg=bar']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
