<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class SshWhoamiTest extends TaskTestCase
{
    // ssh:whoami
    public function test(): void
    {
        $process = $this->runTask(['ssh:whoami']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
