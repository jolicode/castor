<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class NotRenameRenamedTest extends TaskTestCase
{
    // not-rename:renamed
    public function test(): void
    {
        $process = $this->runTask(['not-rename:renamed']);

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
