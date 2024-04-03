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
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
