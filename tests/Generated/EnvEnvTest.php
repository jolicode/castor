<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class EnvEnvTest extends TaskTestCase
{
    // env:env
    public function test(): void
    {
        $process = $this->runTask(['env:env']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
