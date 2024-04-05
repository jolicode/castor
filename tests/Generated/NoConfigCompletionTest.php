<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NoConfigCompletionTest extends TaskTestCase
{
    // completion
    public function test(): void
    {
        $process = $this->runTask(['completion', 'bash'], '/tmp');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
