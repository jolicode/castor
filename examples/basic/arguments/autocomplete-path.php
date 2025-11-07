<?php

namespace arguments;

use Castor\Attribute\AsPathArgument;
use Castor\Attribute\AsTask;

#[AsTask(description: 'Provides autocomplete for a path argument')]
function autocomplete_path(
    #[AsPathArgument(name: 'argument', description: 'This is a path argument with autocompletion')]
    string $argument,
): void {
    var_dump(\func_get_args());
}
