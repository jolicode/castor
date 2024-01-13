<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class WaitForWaitForUrlWithSpecificResponseContentAndStatusTest extends TaskTestCase
{
    // wait-for:wait-for-url-with-specific-response-content-and-status
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-with-specific-response-content-and-status']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
