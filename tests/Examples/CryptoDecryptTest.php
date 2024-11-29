<?php

namespace Castor\Tests\Examples;

use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\TaskTestCase;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CryptoDecryptTest extends TaskTestCase
{
    // crypto:decrypt
    public function test(): void
    {
        $process = $this->runTask(['crypto:decrypt', 'rEg3vPkg1De1I91jmK4cuYlP5Pov1Fm0CVqkG3kFFtwjbSM6zi5yB5UugNppdFkOtiyzcbKr1QbCkF+qa2ymgL8PRw==']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $output = OutputCleaner::cleanOutput($process->getOutput());

        $this->assertSame("hello there\n", $output);
        $this->assertSame('', $process->getErrorOutput());
    }
}
