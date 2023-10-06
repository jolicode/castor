<?php

namespace Castor\Fingerprint;

use Castor\GlobalHelper;

class FingerprintHelper
{
    private const SUFFIX = '.fingerprint';

    public static function verifyFingerprintFromHash(string $fingerprint): bool
    {
        $itemKey = $fingerprint . self::SUFFIX;

        if (false === GlobalHelper::getCache()->hasItem($itemKey)) {
            return false;
        }

        $cacheItem = GlobalHelper::getCache()->getItem($itemKey);

        if (false === $cacheItem->isHit()) {
            return false;
        }

        if ($cacheItem->get() === $fingerprint) {
            return true;
        }

        return false;
    }

    public static function postProcessFingerprintForHash(string $hash): void
    {
        $itemKey = $hash . self::SUFFIX;

        $cacheItem = GlobalHelper::getCache()->getItem($itemKey);

        $cacheItem->set($hash);

        $cacheItem->expiresAt(new \DateTimeImmutable('+1 month'));
        GlobalHelper::getCache()->save($cacheItem);
    }
}
