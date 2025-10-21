<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class NewProjectInitTest extends TaskTestCase
{
    // init
    public function test(): void
    {
        $tmpDirectory = tempnam(sys_get_temp_dir(), '');

        if (file_exists($tmpDirectory)) {
            unlink($tmpDirectory);
        }

        mkdir($tmpDirectory);

        $process = $this->runTask(['init'], $tmpDirectory);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
