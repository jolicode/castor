<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class VersionGuardMinVersionCheckTest extends TaskTestCase
{
    // version-guard:min-version-check
    public function test(): void
    {
        $process = $this->runTask(['version-guard:min-version-check']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
