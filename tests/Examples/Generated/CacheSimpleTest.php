<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class CacheSimpleTest extends TaskTestCase
{
    // cache:simple
    public function test(): void
    {
        $process = $this->runTask(['cache:simple']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
