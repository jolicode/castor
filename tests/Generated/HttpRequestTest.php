<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class HttpRequestTest extends TaskTestCase
{
    // http-request
    public function test(): void
    {
        $process = $this->runTask(['http-request']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
