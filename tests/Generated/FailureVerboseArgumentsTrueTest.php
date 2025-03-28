<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class FailureVerboseArgumentsTrueTest extends TaskTestCase
{
    // failure:verbose-arguments
    public function test(): void
    {
        $process = $this->runTask(['failure:verbose-arguments'], input: 'yes
');

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
