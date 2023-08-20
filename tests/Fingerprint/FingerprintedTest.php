<?php

namespace Castor\Tests\Fingerprint;

trait FingerprintedTest
{
    protected function setUp(): void
    {
        FingerprintCleaner::clearFingerprintsCache();
    }

    public static function tearDownAfterClass(): void
    {
        FingerprintCleaner::clearFingerprintsCache();
    }
}
