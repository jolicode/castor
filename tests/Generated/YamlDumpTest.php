<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class YamlDumpTest extends TaskTestCase
{
    // yaml:dump
    public function test(): void
    {
        $process = $this->runTask(['yaml:dump']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
