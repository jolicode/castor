<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ContextContextInfoForcedTest extends TaskTestCase
{
    // context:context-info-forced
    public function test(): void
    {
        $process = $this->runTask(['context:context-info-forced']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
