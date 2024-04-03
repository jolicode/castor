<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class SymfonyHelloTest extends TaskTestCase
{
    // symfony:hello
    public function test(): void
    {
        if (self::$binary) {
            $this->markTestSkipped('This test is not compatible with the binary version of Castor.');
        }

        $process = $this->runTask(['symfony:hello']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
