<?php

namespace Castor\Tests;

use Symfony\Component\Process\Process;

class ParallelManyProcessesTest extends TaskTestCase
{
    /**
     * Each process sleeps 0.3s: the time parallel() takes must not grow with
     * their number, it used to pause 20ms per process on every round.
     */
    public function testTheTimeDoesNotGrowWithTheNumberOfProcesses(): void
    {
        $this->assertElapsedLessThan(0.8, $this->runTask(['par', '--processes=40'], '{{ base }}/tests/fixtures/valid/parallel-many-processes'));
    }

    public function testANestedParallelDoesNotPauseTwice(): void
    {
        $this->assertElapsedLessThan(0.8, $this->runTask(['nested'], '{{ base }}/tests/fixtures/valid/parallel-many-processes'));
    }

    private function assertElapsedLessThan(float $max, Process $process): void
    {
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertLessThan($max, (float) $process->getOutput());
    }
}
