<?php

namespace cache;

use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;

use function Castor\cache;
use function Castor\io;

#[AsTask(description: 'Cache a simple call')]
function simple(): void
{
    io()->writeln(cache('my-key', fn () => 'SALUT'));
    // Should returns the same things
    io()->writeln(cache('my-key', fn () => 'HELLO'));
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
    io()->writeln(\sprintf('First call: %s', $hasBeenCalled ? 'yes' : 'no'));

    $hasBeenCalled = false;
    cache('another-key', function (CacheItemInterface $item) use (&$hasBeenCalled) {
        $item->expiresAfter(1);
        $hasBeenCalled = true;

        return true;
    });
    io()->writeln(\sprintf('Second call: %s', $hasBeenCalled ? 'yes' : 'no'));
}
