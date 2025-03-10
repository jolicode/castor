<?php

namespace Castor\Tests\Examples;

use Castor\Helper\SymmetricCrypto;
use Castor\Tests\TaskTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CryptoEncryptFileTest extends TaskTestCase
{
    // crypto:encrypt-file
    public function test(): void
    {
        $secretFile = __DIR__ . '/../fixtures/crypto/secret';
        $encryptedFile = $secretFile . '.enc';

        $fs = new Filesystem();

        if ($fs->exists($encryptedFile)) {
            $fs->remove($encryptedFile);
        }

        $fs->dumpFile($secretFile, 'supersecret');

        $process = $this->runTask(['crypto:encrypt-file', $secretFile]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        $this->assertFileExists($encryptedFile);
        $this->assertSame('supersecret', (new SymmetricCrypto(new NullLogger()))->decrypt(file_get_contents($encryptedFile), 'my super secret password'));
        $this->assertSame('', $process->getErrorOutput());
    }
}
