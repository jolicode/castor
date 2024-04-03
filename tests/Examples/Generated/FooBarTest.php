<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class FooBarTest extends TaskTestCase
{
    // foo:bar
    public function test(): void
    {
        $process = $this->runTask(['foo:bar']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
