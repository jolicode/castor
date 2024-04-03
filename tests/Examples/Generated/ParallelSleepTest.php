<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ParallelSleepTest extends TaskTestCase
{
    // parallel:sleep
    public function test(): void
    {
        $process = $this->runTask(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
