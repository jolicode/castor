<?php

namespace Castor\Tests\Generated;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SymfonyGreetTest extends TaskTestCase
{
    // symfony:greet
    public function test(): void
    {
        $process = $this->runTask(['symfony:greet', 'World', '--french', 'COUCOU', '--punctuation', '!']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
