<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ShellShTest extends TaskTestCase
{
    // shell:sh
    public function test(): void
    {
        $process = $this->runTask(['shell:sh']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
