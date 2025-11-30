<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsArgsAfterEndOptionTest extends TaskTestCase
{
    // arguments:args-after-end-option
    public function test(): void
    {
        $process = $this->runTask(['arguments:args-after-end-option', 'FIXME(before)']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
