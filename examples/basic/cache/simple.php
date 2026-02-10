<?php

namespace cache;

use Castor\Attribute\AsTask;

use function Castor\cache;
use function Castor\io;

#[AsTask(description: 'Cache a simple call')]
function simple(): void
{
    io()->writeln(cache('my-key', static fn () => 'SALUT'));
    // Should returns the same things
    io()->writeln(cache('my-key', static fn () => 'HELLO'));
    // Should returns the recomputed value
    io()->writeln(cache('my-key', static fn () => 'HOLA', true));
}
