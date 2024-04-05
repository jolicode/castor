<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ShellBashTest extends TaskTestCase
{
    // shell:bash
    public function test(): void
    {
        $process = $this->runTask(['shell:bash']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
