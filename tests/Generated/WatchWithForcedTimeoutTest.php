<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WatchWithForcedTimeoutTest extends TaskTestCase
{
    // fs-watch
    public function test(): void
    {
        $process = $this->runTask(['fs-watch'], '{{ base }}/tests/fixtures/valid/watch-with-forced-timeout');

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
