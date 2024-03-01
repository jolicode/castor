<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class RunTestFileTest extends TaskTestCase
{
    // run:test-file
    public function test(): void
    {
        $process = $this->runTask(['run:test-file']);

        $this->assertSame(1, $process->getExitCode());
        self::assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            self::assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
