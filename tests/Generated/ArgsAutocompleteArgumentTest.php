<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class ArgsAutocompleteArgumentTest extends TaskTestCase
{
    // args:autocomplete-argument
    public function test(): void
    {
        $process = $this->runTask(['args:autocomplete-argument', 'FIXME(argument)']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
