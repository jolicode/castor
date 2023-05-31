<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class RunRunLsTest extends TaskTestCase
{
    // run:run-ls
    public function testRunRun(): void
    {
        $process = $this->runTask(['run:run-ls']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Output:', $process->getOutput());
        $this->assertStringContainsString('Error output:', $process->getOutput());
        $this->assertStringContainsString('Exit code: 0', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
