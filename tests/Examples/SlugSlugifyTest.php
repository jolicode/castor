<?php

namespace Castor\Tests\Examples;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class SlugSlugifyTest extends TaskTestCase
{
    // slug:slugify
    public function test(): void
    {
        if (self::$binary) {
            $this->markTestSkipped('This test can not be ran with the binary version of Castor.');
        }

        $process = $this->runTask(['slug:slugify']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertStringEqualsFile(__FILE__ . '.output.txt', $process->getOutput());
        $this->assertSame('', $process->getErrorOutput());
    }
}
