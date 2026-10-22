<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ContextFileListWithContextTest extends TaskTestCase
{
    // list
    public function test(): void
    {
        $process = $this->runTask(['list', '--raw', '--context', 'bar'], '{{ base }}/tests/fixtures/valid/context-file');

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
