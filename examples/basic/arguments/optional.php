<?php

namespace arguments;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function optional(string $firstArg, string $secondArg = 'default'): void
{
    io()->writeln($firstArg . ' ' . $secondArg);
}
