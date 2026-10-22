<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SelfUpdateMinVersionNotMetWithCastorFileTest extends TaskTestCase
{
    // --castor-file
    public function test(): void
    {
        $process = $this->runTask(['--castor-file', 'tests/fixtures/broken/min-version-not-met/castor.php', 'self-update', '--rollback']);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
