<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class LogWithContextTest extends TaskTestCase
{
    // log:with-context
    public function testLogWithContext(): void
    {
        $process = $this->runTask(['log:with-context']);
        $this->assertSame(0, $process->getExitCode());
        $this->assertStringContainsString('[castor] Hello, I\'have a context! ["date" => DateTimeImmutable @', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
