<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ContextDisplayDynamicContextInfoTest extends TaskTestCase
{
    // context:display-dynamic-context-info
    public function test(): void
    {
        $process = $this->runTask(['context:display-dynamic-context-info']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
