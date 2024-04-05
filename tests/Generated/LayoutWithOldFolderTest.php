<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;

class LayoutWithOldFolderTest extends TaskTestCase
{
    // list
    public function test(): void
    {
        $process = $this->runTask(['list'], '{{ base }}/tests/fixtures/valid/layout-with-old-folder');

        $this->assertSame(0, $process->getExitCode());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
