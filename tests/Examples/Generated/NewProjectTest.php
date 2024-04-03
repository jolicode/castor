<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class NewProjectTest extends TaskTestCase
{
    // no task
    public function test(): void
    {
        $process = $this->runTask([], '/tmp');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
