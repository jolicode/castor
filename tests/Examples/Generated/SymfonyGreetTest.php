<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class SymfonyGreetTest extends TaskTestCase
{
    // symfony:greet
    public function test(): void
    {
        if (self::$binary) {
            $this->markTestSkipped('This test is not compatible with the binary version of Castor.');
        }

        $process = $this->runTask(['symfony:greet', 'World', '--french', 'COUCOU', '--punctuation', '!']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
