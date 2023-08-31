<?php

namespace Castor\Tests\Fingerprint;

trait FingerprintedTest
{
    public static function tearDownAfterClass(): void
    {
        FingerprintCleaner::clearFingerprintsCache();
    }

    protected function setUp(): void
    {
        FingerprintCleaner::clearFingerprintsCache();
    }
}
