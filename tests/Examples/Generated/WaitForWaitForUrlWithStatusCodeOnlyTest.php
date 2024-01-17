<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class WaitForWaitForUrlWithStatusCodeOnlyTest extends TaskTestCase
{
    // wait-for:wait-for-url-with-status-code-only
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-with-status-code-only']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
