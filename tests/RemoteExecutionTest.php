<?php

namespace Castor\Tests;

use Symfony\Component\Process\Exception\ProcessFailedException;

class RemoteExecutionTest extends TaskTestCase
{
    public function test(): void
    {
        $process = $this->runTask(['execute', 'composer/composer@composer', '-v']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('Installing composer/composer', $process->getErrorOutput());
        $this->assertStringContainsString('Composer version', $process->getOutput());
    }

    public function testQuiet(): void
    {
        $process = $this->runTask(['execute', 'composer/composer@composer']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringNotContainsString('Installing composer/composer', $process->getErrorOutput());
        $this->assertStringContainsString('Composer version', $process->getOutput());
    }

    public function testVersion(): void
    {
        $process = $this->runTask(['execute', 'composer/composer:2.9@composer', '-v']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('Installing composer/composer (2.9', $process->getErrorOutput());
        $this->assertStringContainsString('Composer version 2.9', $process->getOutput());
    }
}
