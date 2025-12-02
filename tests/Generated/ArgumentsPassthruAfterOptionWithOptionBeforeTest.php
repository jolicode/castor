<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsPassthruAfterOptionWithOptionBeforeTest extends TaskTestCase
{
    // arguments:passthru-after-endoption
    public function test(): void
    {
        $process = $this->runTask(['arguments:passthru-after-endoption', 'before', '--custom-option', '--', 'a', 'b']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
