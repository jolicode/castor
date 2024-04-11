<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class VersionGuardMinVersionCheckFailTest extends TaskTestCase
{
    // version-guard:min-version-check-fail
    public function test(): void
    {
        $process = $this->runTask(['version-guard:min-version-check-fail']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
