<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsArgumentsTest extends TaskTestCase
{
    // arguments:arguments
    public function test(): void
    {
        $process = $this->runTask(['arguments:arguments', 'FIXME(word)', '--option', 'default value', '--dry-run']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
