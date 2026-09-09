<?php

namespace Castor\Helper;

use Psr\Log\LoggerInterface;

/**
 * Password-based encryption with libsodium: the key is derived from the
 * password with Argon2id, the content is sealed with XSalsa20-Poly1305.
 *
 * The payload is base64("castor-crypto-v2\0" . opslimit . memlimit . nonce . salt . ciphertext),
 * the limits being 32-bit big-endian integers. The payloads produced before
 * this header existed are base64(nonce . salt . ciphertext), with the
 * "interactive" limits of libsodium, and are still decrypted.
 *
 * @internal
 */
class SymmetricCrypto
{
    private const string MAGIC = "castor-crypto-v2\0";

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function encrypt(#[\SensitiveParameter] string $content, #[\SensitiveParameter] string $password): string
    {
        if (!\extension_loaded('sodium')) {
            throw new \RuntimeException('The sodium extension is required to use crypto functions.');
        }

        if (mb_strlen($password) < 8) {
            $this->logger->warning('The password is too short. It is recommended to use at least 8 characters.');
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $salt = random_bytes(\SODIUM_CRYPTO_PWHASH_SALTBYTES);

        // The "moderate" limits of libsodium: the content is stored, so the
        // derivation is worth more than the "interactive" limits meant for logins
        $opslimit = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
        $memlimit = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;

        $key = sodium_crypto_pwhash(
            \SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            $opslimit,
            $memlimit,
        );

        sodium_memzero($password);

        $encrypted = sodium_crypto_secretbox($content, $nonce, $key);

        sodium_memzero($content);
        sodium_memzero($key);

        return base64_encode(self::MAGIC . pack('NN', $opslimit, $memlimit) . $nonce . $salt . $encrypted);
    }

    public function decrypt(string $encoded, #[\SensitiveParameter] string $password): string
    {
        if (!\extension_loaded('sodium')) {
            throw new \RuntimeException('The sodium extension is required to use crypto functions.');
        }

        $decoded = base64_decode($encoded);

        if (str_starts_with($decoded, self::MAGIC)) {
            $header = unpack('Nopslimit/Nmemlimit', substr($decoded, \strlen(self::MAGIC), 8));

            if (!$header) {
                throw new \RuntimeException('Failed to decrypt the content. Impossible to extract the key derivation limits.');
            }

            $opslimit = $header['opslimit'];
            $memlimit = $header['memlimit'];

            // The limits come from the payload: a crafted one must not be
            // able to exhaust the memory or the CPU, and nothing weaker than
            // the "interactive" limits was ever produced
            if (
                $opslimit < \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE || $opslimit > \SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE
                || $memlimit < \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE || $memlimit > \SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE
            ) {
                throw new \RuntimeException('Failed to decrypt the content. The key derivation limits are not supported.');
            }

            $decoded = substr($decoded, \strlen(self::MAGIC) + 8);
        } else {
            // Encrypted before the header existed
            $opslimit = \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;
            $memlimit = \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        if (\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES !== \strlen($nonce)) {
            throw new \RuntimeException('Failed to decrypt the content. Impossible to extract nonce.');
        }

        $salt = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, \SODIUM_CRYPTO_PWHASH_SALTBYTES);
        if (\SODIUM_CRYPTO_PWHASH_SALTBYTES !== \strlen($salt)) {
            throw new \RuntimeException('Failed to decrypt the content. Impossible to extract salt.');
        }

        $cipherText = substr($decoded, \SODIUM_CRYPTO_PWHASH_SALTBYTES + \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $key = sodium_crypto_pwhash(
            \SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            $opslimit,
            $memlimit,
        );

        sodium_memzero($password);

        $decrypted = sodium_crypto_secretbox_open($cipherText, $nonce, $key);
        if (false === $decrypted) {
            throw new \RuntimeException('Failed to decrypt the content.');
        }

        sodium_memzero($key);

        return $decrypted;
    }
}
