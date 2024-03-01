<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class VersionGuardMinVersionCheckFailTest extends TaskTestCase
{
    // version-guard:min-version-check-fail
    public function test(): void
    {
        $process = $this->runTask(['version-guard:min-version-check-fail']);

        $this->assertSame(1, $process->getExitCode());
        self::assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        if (file_exists(__FILE__ . '.err.txt')) {
            self::assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
