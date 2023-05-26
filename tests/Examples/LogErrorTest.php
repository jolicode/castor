<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class LogErrorTest extends TaskTestCase
{
    // log:error
    public function testLogError(): void
    {
        $process = $this->runTask(['log:error']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('ERROR     [castor] Error!, this is an "error" log message.', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
