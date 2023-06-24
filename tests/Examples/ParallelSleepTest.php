<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class ParallelSleepTest extends TaskTestCase
{
    // parallel:sleep
    public function test(): void
    {
        $process = $this->runTask(['parallel:sleep', '--sleep5', '0', '--sleep7', '0', '--sleep10', '0', '--no-trust']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
