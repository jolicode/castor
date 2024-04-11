<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class WaitForWaitForUrlWithSpecificResponseContentAndStatusTest extends TaskTestCase
{
    // wait-for:wait-for-url-with-specific-response-content-and-status
    public function test(): void
    {
        $process = $this->runTask(['wait-for:wait-for-url-with-specific-response-content-and-status']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
