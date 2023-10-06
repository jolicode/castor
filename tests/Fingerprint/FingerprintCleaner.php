<?php

namespace Castor\Tests\Fingerprint;

use Symfony\Component\Finder\Finder;

class FingerprintCleaner
{
    public static function clearFingerprintsCache(): void
    {
        if (is_dir('/tmp/castor')) {
            foreach (
                (new Finder())
                    ->in('/tmp/castor')
                    ->contains('.fingerprint')
                    ->files() as $file
            ) {
                unlink($file->getRealPath());
            }

            foreach (
                (new Finder())
                    ->in('/tmp/castor')
                    ->notContains('.fingerprint')
                    ->directories() as $directory
            ) {
                rmdir($directory->getRealPath());
            }
        }

        $examplesFingerprintFile = (new Finder())
            ->in(\dirname(__DIR__, 2) . '/examples')
            ->name('*.fingerprint_*')
            ->notName('*.php')
        ;

        foreach ($examplesFingerprintFile as $file) {
            unlink($file->getRealPath());
        }
    }
}
