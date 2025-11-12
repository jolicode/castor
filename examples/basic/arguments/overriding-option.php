<?php

namespace arguments;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function override_option(
    #[AsOption(name: 'foo', description: 'This is the foo option')]
    string $arg = 'bar',
): void {
    io()->writeln($arg);
}
