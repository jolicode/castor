<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SshLsTest extends TaskTestCase
{
    // ssh:ls
    public function test(): void
    {
        $process = $this->runTask(['ssh:ls']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
