<?php

namespace Castor\Fingerprint;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

/** @internal */
class FingerprintHelper
{
    private const SUFFIX = '.fingerprint';

    public function __construct(
        private readonly CacheItemPoolInterface&CacheInterface $cache,
    ) {
    }

    public function verifyFingerprintFromHash(string $id, string $fingerprint): bool
    {
        $itemKey = $id . self::SUFFIX;

        if (false === $this->cache->hasItem($itemKey)) {
            return false;
        }

        $cacheItem = $this->cache->getItem($itemKey);

        if (false === $cacheItem->isHit()) {
            return false;
        }

        if ($cacheItem->get() === $fingerprint) {
            return true;
        }

        return false;
    }

    public function postProcessFingerprintForHash(string $id, string $hash): void
    {
        $itemKey = $id . self::SUFFIX;

        $cacheItem = $this->cache->getItem($itemKey);

        $cacheItem->set($hash);

        $cacheItem->expiresAt(new \DateTimeImmutable('+1 month'));
        $this->cache->save($cacheItem);
    }
}
