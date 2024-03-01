<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ArgsAnotherArgsTest extends TaskTestCase
{
    // args:another-args
    public function test(): void
    {
        $process = $this->runTask(['args:another-args', 'FIXME(required)', '--test2', 1]);

        $this->assertSame(0, $process->getExitCode());
        self::assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            self::assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
