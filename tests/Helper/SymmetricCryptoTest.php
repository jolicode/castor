<?php

namespace Castor\Tests\Helper;

use Castor\Helper\SymmetricCrypto;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SymmetricCryptoTest extends TestCase
{
    private const string PASSWORD = 'my super secret password';

    // Encrypted "hello there" with the format that predates the header
    private const string LEGACY_PAYLOAD = 'rEg3vPkg1De1I91jmK4cuYlP5Pov1Fm0CVqkG3kFFtwjbSM6zi5yB5UugNppdFkOtiyzcbKr1QbCkF+qa2ymgL8PRw==';

    protected function setUp(): void
    {
        if (!\extension_loaded('sodium')) {
            $this->markTestSkipped('The sodium extension is not loaded.');
        }
    }

    public function testRoundTrip(): void
    {
        $crypto = new SymmetricCrypto(new NullLogger());

        $encrypted = $crypto->encrypt('hello there', self::PASSWORD);

        $this->assertStringStartsWith("castor-crypto-v2\0", (string) base64_decode($encrypted, true));
        $this->assertSame('hello there', $crypto->decrypt($encrypted, self::PASSWORD));
    }

    public function testTheKeyIsDerivedWithTheModerateLimits(): void
    {
        $crypto = new SymmetricCrypto(new NullLogger());

        $header = unpack('Nopslimit/Nmemlimit', substr((string) base64_decode($crypto->encrypt('hello there', self::PASSWORD), true), \strlen("castor-crypto-v2\0"), 8));

        $this->assertSame(\SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, $header['opslimit'] ?? null);
        $this->assertSame(\SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE, $header['memlimit'] ?? null);
    }

    public function testContentEncryptedBeforeTheHeaderExistedIsStillDecrypted(): void
    {
        $this->assertSame('hello there', new SymmetricCrypto(new NullLogger())->decrypt(self::LEGACY_PAYLOAD, self::PASSWORD));
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $crypto = new SymmetricCrypto(new NullLogger());
        $encrypted = $crypto->encrypt('hello there', self::PASSWORD);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt the content.');

        $crypto->decrypt($encrypted, 'not the password');
    }

    public function testUnsupportedKeyDerivationLimitsAreRejected(): void
    {
        $crypto = new SymmetricCrypto(new NullLogger());
        $decoded = (string) base64_decode($crypto->encrypt('hello there', self::PASSWORD), true);

        // 2 GiB of memory, twice the "sensitive" limit of libsodium
        $crafted = base64_encode(substr($decoded, 0, \strlen("castor-crypto-v2\0")) . pack('NN', 3, 2 * 1024 ** 3) . substr($decoded, \strlen("castor-crypto-v2\0") + 8));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key derivation limits are not supported');

        $crypto->decrypt($crafted, self::PASSWORD);
    }
}
