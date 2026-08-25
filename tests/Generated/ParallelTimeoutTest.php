<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ParallelTimeoutTest extends TaskTestCase
{
    // parallel-timeout
    public function test(): void
    {
        $process = $this->runTask(['parallel-timeout'], '{{ base }}/tests/fixtures/valid/parallel-timeout');

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
