<?php

namespace Castor\Tests\Examples;

use Castor\Tests\OutputCleaner;
use Castor\Tests\TaskTestCase;

class CdDirectoryTest extends TaskTestCase
{
    // cd:directory
    public function testCdDirectory(): void
    {
        $process = $this->runTask(['cd:directory', '--no-trust']);
        $this->assertSame(0, $process->getExitCode());
        $output = OutputCleaner::cleanOutput($process->getOutput());
        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $output);
        if (file_exists(__FILE__ . '.err.txt')) {
            $this->assertStringEqualsFile(__FILE__ . '.err.txt', OutputCleaner::cleanOutput($process->getErrorOutput()));
        } else {
            $this->assertSame('', $process->getErrorOutput());
        }
    }
}
