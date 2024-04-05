<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ListTest extends TaskTestCase
{
    // list
    public function test(): void
    {
        if (self::$binary) {
            $this->markTestSkipped('This test is not compatible with the binary version of Castor.');
        }

        $process = $this->runTask(['list', '--raw', '--format', 'txt', '--short'], needRemote: true);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
