<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class BarBarTest extends TaskTestCase
{
    // bar:bar
    public function test(): void
    {
        $process = $this->runTask(['bar:bar']);

        $this->assertSame(0, $process->getExitCode());
        self::assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            self::assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
