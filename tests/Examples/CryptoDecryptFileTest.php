<?php

namespace Castor\Tests\Examples;

use Castor\Helper\SymmetricCrypto;
use Castor\Tests\TaskTestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CryptoDecryptFileTest extends TaskTestCase
{
    private const CONTENT = 'supersecret';
    private const FIXTURES_DIR = '/../fixtures/crypto/';
    private Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new Filesystem();
    }

    /**
     * Decrypting a file with .enc extension when the target file doesn't exist. Creates a file without the .enc extension.
     */
    public function testDecryptEncFile(): void
    {
        $decryptedSecretFile = __DIR__ . self::FIXTURES_DIR . 'secret_1';
        $encryptedSecretFile = $decryptedSecretFile . '.enc';

        $this->fs->remove($decryptedSecretFile);

        $this->createEncryptedFile($encryptedSecretFile);

        $process = $this->runDecryptTask($encryptedSecretFile);

        $this->assertFileExists($decryptedSecretFile);
        $this->assertSame(self::CONTENT, file_get_contents($decryptedSecretFile));
        $this->assertSame('', $process->getErrorOutput());
    }

    /**
     * Decrypting a file with .enc extension when the target file already exists. Creates a file with .dec extension.
     */
    public function testDecryptEncFileWhenOriginalFileExists(): void
    {
        $existingDecryptedSecretFile = __DIR__ . self::FIXTURES_DIR . 'secret_1';
        $encryptedSecretFile = $existingDecryptedSecretFile . '.enc';
        $decryptedSecretFile = $existingDecryptedSecretFile . '.dec';

        $this->fs->remove($decryptedSecretFile);
        $this->fs->dumpFile($existingDecryptedSecretFile, self::CONTENT);

        $this->createEncryptedFile($encryptedSecretFile);

        $process = $this->runDecryptTask($encryptedSecretFile);

        $this->assertFileExists($decryptedSecretFile);
        $this->assertSame(self::CONTENT, file_get_contents($decryptedSecretFile));
        $this->assertSame('', $process->getErrorOutput());
    }

    /**
     * Decrypting a file without .enc extension. Creates a file with .dec extension.
     */
    public function testDecryptFile(): void
    {
        $encryptedSecretFile = __DIR__ . self::FIXTURES_DIR . 'enc_secret_2';
        $decryptedSecretFile = $encryptedSecretFile . '.dec';

        $this->fs->remove($decryptedSecretFile);
        $this->createEncryptedFile($encryptedSecretFile);

        $process = $this->runDecryptTask($encryptedSecretFile);

        $this->assertFileExists($decryptedSecretFile);
        $this->assertSame(self::CONTENT, file_get_contents($decryptedSecretFile));
        $this->assertSame('', $process->getErrorOutput());
    }

    private function runDecryptTask(string $filePath): Process
    {
        $process = $this->runTask(['crypto:decrypt-file', $filePath]);

        if (0 !== $process->getExitCode()) {
            throw new ProcessFailedException($process);
        }

        return $process;
    }

    private function createEncryptedFile(string $filePath): void
    {
        $symmetricCrypto = new SymmetricCrypto(new NullLogger());
        $encryptedContent = $symmetricCrypto->encrypt(self::CONTENT, 'my super secret password');
        $this->fs->dumpFile($filePath, $encryptedContent);
    }
}
