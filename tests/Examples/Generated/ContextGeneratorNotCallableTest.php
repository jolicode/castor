<?php

namespace Castor\Tests\Examples\Generated;

use Castor\Tests\TaskTestCase;

class ContextGeneratorNotCallableTest extends TaskTestCase
{
    // no task
    public function test(): void
    {
        $process = $this->runTask([], '{{ base }}/tests/Examples/fixtures/broken/context-generator-not-callable', needRemote: true);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.err.txt', $process->getErrorOutput());
    }
}
