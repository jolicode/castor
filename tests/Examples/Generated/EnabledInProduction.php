<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class EnabledInProduction extends TaskTestCase
{
    // enabled:hello
    public function test(): void
    {
        $process = $this->runTask(['enabled:hello', '--context', 'production']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
