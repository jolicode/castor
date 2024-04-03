<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ArgsArgsTest extends TaskTestCase
{
    // args:args
    public function test(): void
    {
        $process = $this->runTask(['args:args', 'FIXME(word)', '--option', 'default value', '--dry-run']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
