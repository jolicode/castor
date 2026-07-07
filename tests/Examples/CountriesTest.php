<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CountriesTest extends TaskTestCase
{
    // countries:choice
    public function testCountryCodes(): void
    {
        $process = $this->runTask(['countries:choice']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('FR', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    // locales:exist
    public function testCountryExists(): void
    {
        $process = $this->runTask(['countries:exist', 'FR']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('The country "FR" does exist', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
