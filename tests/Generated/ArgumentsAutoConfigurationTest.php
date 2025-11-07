<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsAutoConfigurationTest extends TaskTestCase
{
    // arguments:auto-configuration
    public function test(): void
    {
        $process = $this->runTask(['arguments:auto-configuration', 'FIXME(required)', '--count', 1]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
