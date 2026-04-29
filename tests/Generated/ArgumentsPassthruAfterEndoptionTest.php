<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsPassthruAfterEndoptionTest extends TaskTestCase
{
    // arguments:passthru-after-endoption
    public function test(): void
    {
        $process = $this->runTask(['arguments:passthru-after-endoption', 'FIXME(before-arg)', '--custom-option']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
