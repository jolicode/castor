<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class CacheComplexTest extends TaskTestCase
{
    // cache:complex
    public function test(): void
    {
        $process = $this->runTask(['cache:complex']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
