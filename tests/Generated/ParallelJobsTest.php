<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ParallelJobsTest extends TaskTestCase
{
    // parallel:jobs
    public function test(): void
    {
        $process = $this->runTask(['parallel:jobs', '--fail']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
