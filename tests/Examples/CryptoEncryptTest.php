<?php

namespace Castor\Tests\Examples;

use Castor\Helper\SymmetricCrypto;
use Castor\Tests\Helper\OutputCleaner;
use Castor\Tests\TaskTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CryptoEncryptTest extends TaskTestCase
{
    // crypto:encrypt
    public function test(): void
    {
        $process = $this->runTask(['crypto:encrypt', 'hello there']);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $output = OutputCleaner::cleanOutput($process->getOutput());

        $this->assertSame(93, \strlen($output));
        $this->assertSame('hello there', (new SymmetricCrypto(new NullLogger()))->decrypt($output, 'my super secret password'));
        $this->assertSame('', $process->getErrorOutput());
    }
}
