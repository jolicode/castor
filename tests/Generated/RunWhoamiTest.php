<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class RunWhoamiTest extends TaskTestCase
{
    // run:whoami
    public function test(): void
    {
        $process = $this->runTask(['run:whoami']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
