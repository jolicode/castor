<?php

namespace Castor\Fingerprint;

use Castor\Helper\PathHelper;
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

    public function verifyFingerprintFromHash(string $id, string $fingerprint, bool $global = false): bool
    {
        $itemKey = $this->getItemKey($id, $global);

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

    public function postProcessFingerprintForHash(string $id, string $hash, bool $global = false): void
    {
        $itemKey = $this->getItemKey($id, $global);

        $cacheItem = $this->cache->getItem($itemKey);

        $cacheItem->set($hash);

        $cacheItem->expiresAt(new \DateTimeImmutable('+1 month'));
        $this->cache->save($cacheItem);
    }

    private function getItemKey(string $id, bool $global = false): string
    {
        if ($global) {
            return $id . self::SUFFIX;
        }

        return \sprintf(
            '%s-%s',
            hash('xxh128', PathHelper::getRoot()),
            $id,
        );
    }
}
