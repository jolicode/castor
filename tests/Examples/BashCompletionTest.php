<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BashCompletionTest extends TaskTestCase
{
    // _complete
    public function test(): void
    {
        $process = $this->runTask(['_complete', '--no-interaction', '-sbash', '-c1', '-a1', '-icastor', '-ihel']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('hello', $process->getOutput());
    }
}
