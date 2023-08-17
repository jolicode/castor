<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;

class ArgsArgsTest extends TaskTestCase
{
    // args:args
    public function test(): void
    {
        $process = $this->runTask(['args:args', 'FIXME(word)', '--option', 'default value', '--dry-run', '--force']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
