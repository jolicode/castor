<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CurrenciesTest extends TaskTestCase
{
    // currencies:choice
    public function testCurrencyCodes(): void
    {
        $process = $this->runTask(['currencies:choice']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('EUR', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }

    // currencies:exist
    public function testCurrencyExists(): void
    {
        $process = $this->runTask(['currencies:exist', 'EUR']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringContainsString('The currency "EUR" does exist', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
