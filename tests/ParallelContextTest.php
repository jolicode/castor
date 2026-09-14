<?php

namespace Castor\Tests;

class ParallelContextTest extends TaskTestCase
{
    public function testWithInsideParallelDoesNotLeakTheContextBetweenFibers(): void
    {
        $process = $this->runTask(['par'], '{{ base }}/tests/fixtures/valid/parallel-context');

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertSame(<<<'TXT'
            a after resume: context=a run=a
            b after resume: context=b
            after parallel: context=parallel-context

            TXT, $process->getOutput());
    }
}
