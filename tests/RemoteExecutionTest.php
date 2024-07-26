<?php

namespace Castor\Tests;

use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteExecutionTest extends TaskTestCase
{
    public function test(): void
    {
        $process = $this->runTask(['execute', 'composer/composer@composer']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('Installing composer/composer', $process->getErrorOutput());
        $this->assertStringContainsString('Composer version', $process->getOutput());
    }

    public function testVersion(): void
    {
        $process = $this->runTask(['execute', 'composer/composer:2.7@composer']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('Installing composer/composer (2.7', $process->getErrorOutput());
        $this->assertStringContainsString('Composer version 2.7', $process->getOutput());
    }
}
