<?php

namespace arguments;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function override_argument(
    #[AsArgument(name: 'foo', description: 'This is the foo argument')]
    string $arg = 'bar',
): void {
    io()->writeln($arg);
}
