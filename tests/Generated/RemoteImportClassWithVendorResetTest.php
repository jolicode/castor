<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteImportClassWithVendorResetTest extends TaskTestCase
{
    // remote-import:remote-task-class
    public function test(): void
    {
        $process = $this->runTask(['remote-import:remote-task-class'], needRemote: true, needResetVendor: true);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
