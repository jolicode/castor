<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class LogInfoTest extends TaskTestCase
{
    // log:info
    public function testLogInfo(): void
    {
        $process = $this->runTask(['log:info']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Re-run with -vv, -vvv for different output.', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    // log:info -v
    public function testLogInfo2(): void
    {
        $process = $this->runTask(['log:info', '-v']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('Re-run with -vv, -vvv for different output.', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    // log:info -vv
    public function testLogInfo3(): void
    {
        $process = $this->runTask(['log:info', '-vv']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('INFO      [castor] Hello, this is an "info" log message.', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
