<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class AssertionEnsureWeAreInTheFutureTest extends TaskTestCase
{
    // assertion:ensure-we-are-in-the-future
    public function test(): void
    {
        $process = $this->runTask(['assertion:ensure-we-are-in-the-future']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
