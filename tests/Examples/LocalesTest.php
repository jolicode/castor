<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class LocalesTest extends TaskTestCase
{
    // locales:choice
    public function testLocaleCodes(): void
    {
        $process = $this->runTask(['locales:choice']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('fr_FR', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    // locales:exist
    public function testLocaleExists(): void
    {
        $process = $this->runTask(['locales:exist', 'fr_FR']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('The locale "fr_FR" does exist', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
