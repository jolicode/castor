<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ArgumentsPathOptionWithFilterTest extends TaskTestCase
{
    // arguments:path-option-with-filter
    public function test(): void
    {
        $process = $this->runTask(['arguments:path-option-with-filter', '--file', 'FIXME']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFileWithCleaning(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
