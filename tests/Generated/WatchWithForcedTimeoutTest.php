<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class WatchWithForcedTimeoutTest extends TaskTestCase
{
    // fs-watch
    public function test(): void
    {
        $process = $this->runTask(['fs-watch'], '{{ base }}/tests/fixtures/valid/watch-with-forced-timeout');

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
