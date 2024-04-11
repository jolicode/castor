<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgsAutocompleteArgumentTest extends TaskTestCase
{
    // args:autocomplete-argument
    public function test(): void
    {
        $process = $this->runTask(['args:autocomplete-argument', 'FIXME(argument)']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
