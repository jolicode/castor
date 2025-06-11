<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ContextIsParsedAnywhereOnTheCommandLineTest extends TaskTestCase
{
    // context-info
    public function test(): void
    {
        $process = $this->runTask(['context-info', '-c', 'run', '--test']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
