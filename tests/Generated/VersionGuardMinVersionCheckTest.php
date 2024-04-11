<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VersionGuardMinVersionCheckTest extends TaskTestCase
{
    // version-guard:min-version-check
    public function test(): void
    {
        $process = $this->runTask(['version-guard:min-version-check']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
