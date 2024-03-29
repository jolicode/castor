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
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
