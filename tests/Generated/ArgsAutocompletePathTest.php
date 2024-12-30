<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgsAutocompletePathTest extends TaskTestCase
{
    // args:autocomplete-path
    public function test(): void
    {
        $process = $this->runTask(['args:autocomplete-path', 'FIXME(argument)']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
