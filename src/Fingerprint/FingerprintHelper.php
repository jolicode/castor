<?php

namespace Castor\Fingerprint;

use Castor\Attribute\AsTask;
use Castor\GlobalHelper;

/** @internal */
class FingerprintHelper
{
    private const SUFFIX = '.fingerprint';

    public static function postProcessFingerprintForTask(AsTask $taskAttribute): void
    {
        if (null === $taskAttribute->fingerprint) {
            return;
        }

        $fingerprint = self::getFingerprintFromCallable($taskAttribute->fingerprint);
        $itemKey = GlobalHelper::getApplication()->getName() . self::SUFFIX;

        self::compareFingerprint($itemKey, $fingerprint);
    }

    public static function verifyTaskFingerprintFromTaskAttribute(AsTask $taskAttribute): bool
    {
        if (null === $taskAttribute->fingerprint) {
            return true;
        }

        $itemKey = GlobalHelper::getApplication()->getName() . self::SUFFIX;
        $fingerprint = self::getFingerprintFromCallable($taskAttribute->fingerprint);

        return self::checkFingerprintIsInCache($itemKey, $fingerprint);
    }

    public static function verifyFingerprintFromHash(string $fingerprint): bool
    {
        $itemKey = $fingerprint . self::SUFFIX;

        return self::checkFingerprintIsInCache($itemKey, $fingerprint);
    }

    public static function postProcessFingerprintForHash(string $hash): void
    {
        $itemKey = $hash . self::SUFFIX;

        self::compareFingerprint($itemKey, $hash);
    }

    private static function getFingerprintFromCallable(callable $callable): string
    {
        $hash = \call_user_func($callable);

        if (!\is_string($hash)) {
            throw new \LogicException('The fingerprint callable must return a string');
        }

        return $hash;
    }

    private static function checkFingerprintIsInCache(string $itemKey, string $fingerprint): bool
    {
        if (false === GlobalHelper::getCache()->hasItem($itemKey)) {
            return true;
        }

        $cacheItem = GlobalHelper::getCache()->getItem($itemKey);

        if (false === $cacheItem->isHit()) {
            return true;
        }

        return $cacheItem->get() !== $fingerprint;
    }

    private static function compareFingerprint(string $itemKey, string $fingerprint): void
    {
        $cacheItem = GlobalHelper::getCache()->getItem($itemKey);

        if ($cacheItem->get() !== $fingerprint) {
            $cacheItem->set($fingerprint);
        }

        $cacheItem->expiresAt(new \DateTimeImmutable('+1 month'));
        GlobalHelper::getCache()->save($cacheItem);
    }
}
