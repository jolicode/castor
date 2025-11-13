<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ContextUseContextWithConfigFlagsTest extends TaskTestCase
{
    // context:use-context-with-config-flags
    public function test(): void
    {
        $process = $this->runTask(['context:use-context-with-config-flags']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
