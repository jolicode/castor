<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class FilesystemFilesystemTest extends TaskTestCase
{
    // filesystem:filesystem
    public function test(): void
    {
        $process = $this->runTask(['filesystem:filesystem']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
