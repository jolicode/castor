<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class LayoutWithOldFolderTest extends TaskTestCase
{
    // list
    public function test(): void
    {
        $process = $this->runTask(['list'], '{{ base }}/tests/fixtures/valid/layout-with-old-folder');

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
