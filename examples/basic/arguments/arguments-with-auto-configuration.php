<?php

namespace arguments;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Dumps all arguments and options, without configuration')]
function auto_configuration(
    string $required,
    int $count = 1,
): void {
    io()->writeln($required . ' ' . $count);
}
