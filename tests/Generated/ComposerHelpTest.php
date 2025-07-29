<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ComposerHelpTest extends TaskTestCase
{
    // composer
    public function test(): void
    {
        $process = $this->runTask(['composer', 'list', '--', '--help']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
