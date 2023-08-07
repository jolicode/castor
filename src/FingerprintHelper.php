<?php

namespace Castor;

use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Finder\Finder;

/** @internal */
class FingerprintHelper
{
    private static ?string $filenamesFingerprints = null;

    private static ?string $filenamesContentFingerprints = null;

    /**
     * @throws InvalidArgumentException
     */
    public static function executeIfFingerprintIsDifferent(Finder $finder, callable $callback): bool
    {
        if (self::getFingerprintsFromFinder($finder)) {
            $callback();
            self::postProcessFingerprint();

            return true;
        }

        return false;
    }

    /**
     * @return bool true if the command can be executed, false if the command cannot be executed
     *
     * @throws InvalidArgumentException
     */
    public static function getFingerprintsFromTaskAttribute(AsTask $taskAttribute): bool
    {
        self::resetFingerprints();

        if (null === $taskAttribute->fingerprint) {
            return true;
        }

        $finder = self::getFinderInstanceFromTaskAttribute($taskAttribute);

        return self::getFingerprintsFromFinder($finder);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function postProcessFingerprint(): void
    {
        if (null === self::$filenamesFingerprints) {
            return;
        }
        GlobalHelper::getCache()->delete(self::$filenamesFingerprints);
        GlobalHelper::getCache()->get(self::$filenamesFingerprints, function (CacheItemInterface $cacheItem) {
            $cacheItem->expiresAt((new \DateTime())->modify('+1 year'));
            $cacheItem->set(self::$filenamesContentFingerprints);

            return self::$filenamesContentFingerprints;
        });
    }

    /**
     * @return bool true if the command can be executed, false if the command cannot be executed
     *
     * @throws InvalidArgumentException
     */
    private static function getFingerprintsFromFinder(Finder $finder): bool
    {
        $files = iterator_to_array($finder);

        self::generateFingerprints($files);

        if (false === self::isFingerprintInCache()) {
            return true;
        }

        if (null === self::$filenamesFingerprints) {
            return true;
        }
        $cacheItemFilenamesFingerprint = GlobalHelper::getCache()->getItem(self::$filenamesFingerprints)->get();

        if (self::$filenamesContentFingerprints !== $cacheItemFilenamesFingerprint) {
            return true;
        }

        return false;
    }

    private static function getFinderInstanceFromTaskAttribute(AsTask $taskAttribute): Finder
    {
        $callable = $taskAttribute->fingerprint;
        if (!\is_callable($callable)) {
            throw new \LogicException(sprintf('The fingerprint function "%s" must be callable.', $taskAttribute->fingerprint));
        }
        $finder = $callable();
        if (!$finder instanceof Finder) {
            throw new \LogicException(sprintf('The fingerprint function "%s" must return a %s instance.', $taskAttribute->fingerprint, Finder::class));
        }

        return $finder;
    }

    private static function resetFingerprints(): void
    {
        self::$filenamesFingerprints = null;
        self::$filenamesContentFingerprints = null;
    }

    /**
     * @param array<\SplFileInfo> $files
     */
    private static function generateFingerprints(array $files): void
    {
        self::$filenamesFingerprints = md5(
            implode('', array_map(fn (\SplFileInfo $file) => $file->getFilename(), $files))
        );
        self::$filenamesContentFingerprints = implode(
            '',
            array_map(fn (\SplFileInfo $file) => md5_file($file->getPathname()), $files)
        );
    }

    private static function isFingerprintInCache(): bool
    {
        if (null === self::$filenamesFingerprints) {
            return false;
        }

        return GlobalHelper::getCache()->hasItem(self::$filenamesFingerprints);
    }
}
