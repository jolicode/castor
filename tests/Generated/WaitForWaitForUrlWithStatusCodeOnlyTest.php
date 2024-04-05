<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class WaitForWaitForUrlWithStatusCodeOnlyTest extends TaskTestCase
{
    // wait-for:wait-for-url-with-status-code-only
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-with-status-code-only']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
