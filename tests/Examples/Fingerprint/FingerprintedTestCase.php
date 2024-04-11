<?php

namespace Castor\Tests\Examples\Fingerprint;

use Castor\Tests\TaskTestCase;
use Symfony\Component\Finder\Finder;

abstract class FingerprintedTestCase extends TaskTestCase
{
    public static function tearDownAfterClass(): void
    {
        self::clearFingerprintsCache();
    }

    protected function setUp(): void
    {
        self::clearFingerprintsCache();
    }

    private static function clearFingerprintsCache(): void
    {
        $cacheDir = self::$castorCacheDir;

        if (is_dir($cacheDir)) {
            foreach (
                (new Finder())
                    ->in($cacheDir)
                    ->contains('.fingerprint')
                    ->files() as $file
            ) {
                unlink($file->getRealPath());
            }

            foreach (
                (new Finder())
                    ->in($cacheDir)
                    ->notContains('.fingerprint')
                    ->directories() as $directory
            ) {
                rmdir($directory->getRealPath());
            }
        }

        $examplesFingerprintFile = (new Finder())
            ->in(\dirname(__DIR__, 3) . '/examples')
            ->name('*.fingerprint_*')
            ->notName('*.php')
        ;

        foreach ($examplesFingerprintFile as $file) {
            unlink($file->getRealPath());
        }
    }
}
