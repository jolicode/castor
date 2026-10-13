<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class LlmAskTest extends TaskTestCase
{
    // ask
    public function test(): void
    {
        $process = $this->runTask(['ask'], '{{ base }}/tests/fixtures/valid/llm');

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
