<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class SshUploadTest extends TaskTestCase
{
    // ssh:upload
    public function test(): void
    {
        $process = $this->runTask(['ssh:upload']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
