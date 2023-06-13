<?php

namespace cache;

use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;

use function Castor\cache;

#[AsTask(description: 'Cache a simple call')]
function simple(): void
{
    echo cache('my-key', fn () => strip_tags((string) file_get_contents('https://perdu.com/'))) . "\n";
    // Should returns the same things
    echo cache('my-key', fn () => strip_tags((string) file_get_contents('https://estcequecestuntempsaraclette.fr/'))) . "\n";
}

#[AsTask(description: 'Cache with usage of CacheItemInterface')]
function complex(): void
{
    $hasBeenCalled = false;
    cache('another-key', function (CacheItemInterface $item) use (&$hasBeenCalled) {
        $item->expiresAfter(1);
        $hasBeenCalled = true;

        return true;
    });
    echo sprintf("First call: %s\n", $hasBeenCalled ? 'yes' : 'no');

    $hasBeenCalled = false;
    cache('another-key', function (CacheItemInterface $item) use (&$hasBeenCalled) {
        $item->expiresAfter(1);
        $hasBeenCalled = true;

        return true;
    });
    echo sprintf("Second call: %s\n", $hasBeenCalled ? 'yes' : 'no');
}
