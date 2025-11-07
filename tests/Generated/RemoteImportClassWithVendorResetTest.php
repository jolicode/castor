<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteImportClassWithVendorResetTest extends TaskTestCase
{
    // remote-import:import-class
    public function test(): void
    {
        $process = $this->runTask(['remote-import:import-class'], needRemote: true, needResetVendor: true);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
