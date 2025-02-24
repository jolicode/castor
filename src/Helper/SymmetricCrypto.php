<?php

namespace Castor\Helper;

use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class SymmetricCrypto
{
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

        $key = sodium_crypto_pwhash(
            \SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
            $password,
            $salt,
            \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );

        sodium_memzero($password);

        $encrypted = sodium_crypto_secretbox($content, $nonce, $key);

        sodium_memzero($content);
        sodium_memzero($key);

        return base64_encode($nonce . $salt . $encrypted);
    }

    public function decrypt(string $encoded, #[\SensitiveParameter] string $password): string
    {
        if (!\extension_loaded('sodium')) {
            throw new \RuntimeException('The sodium extension is required to use crypto functions.');
        }

        $decoded = base64_decode($encoded);

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
            \SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            \SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
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
