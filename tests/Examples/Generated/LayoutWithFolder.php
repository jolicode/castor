<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class LayoutWithFolder extends TaskTestCase
{
    // list
    public function test(): void
    {
        $process = $this->runTask(['list'], '/home/gregoire/dev/github.com/jolicode/castor/bin/../tests/Examples/fixtures/layout/with-folder');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
