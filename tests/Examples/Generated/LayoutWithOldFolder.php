<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class LayoutWithOldFolder extends TaskTestCase
{
    // list
    public function test(): void
    {
        $process = $this->runTask(['list'], '/home/gregoire/dev/github.com/jolicode/castor/bin/../tests/Examples/fixtures/layout/with-old-folder');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
