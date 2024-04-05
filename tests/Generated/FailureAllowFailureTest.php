<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class FailureAllowFailureTest extends TaskTestCase
{
    // failure:allow-failure
    public function test(): void
    {
        $process = $this->runTask(['failure:allow-failure']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
