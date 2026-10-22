<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SelfUpdateMinVersionNotMetWithContextTest extends TaskTestCase
{
    // -c
    public function test(): void
    {
        $process = $this->runTask(['-c', 'bar', 'self-update', '--rollback'], '{{ base }}/tests/fixtures/broken/min-version-not-met');

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
