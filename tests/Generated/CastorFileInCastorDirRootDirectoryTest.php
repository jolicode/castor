<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CastorFileInCastorDirRootDirectoryTest extends TaskTestCase
{
    // --castor-file
    public function test(): void
    {
        $process = $this->runTask(['--castor-file', 'tests/fixtures/valid/castor-file-in-castor-dir/.castor/my-castor.php', 'root']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
