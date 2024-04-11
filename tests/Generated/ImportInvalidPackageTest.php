<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ImportInvalidPackageTest extends TaskTestCase
{
    // no task
    public function test(): void
    {
        $process = $this->runTask([], '{{ base }}/tests/fixtures/broken/import-invalid-package', needRemote: true);

        if (1 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
