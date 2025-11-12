<?php

namespace arguments;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function simple(string $firstArg, string $secondArg): void {
    io()->writeln($firstArg . ' ' . $secondArg);
}
