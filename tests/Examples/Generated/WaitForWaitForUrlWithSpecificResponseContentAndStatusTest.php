<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class WaitForWaitForUrlWithSpecificResponseContentAndStatusTest extends TaskTestCase
{
    // wait-for:wait-for-url-with-specific-response-content-and-status
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-with-specific-response-content-and-status']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
