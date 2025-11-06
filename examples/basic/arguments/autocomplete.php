<?php

namespace arguments;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Completion\CompletionInput;

#[AsTask(description: 'Provides autocomplete for an argument')]
function autocomplete(
    #[AsArgument(name: 'argument', description: 'This is an argument with autocompletion', autocomplete: 'arguments\get_argument_autocompletion')]
    string $argument,
): void {
    var_dump(\func_get_args());
}

/** @return string[] */
function get_argument_autocompletion(CompletionInput $input): array
{
    // You can search for a file on the filesystem, make a network call, etc.

    return [
        'foo',
        'bar',
        'baz',
    ];
}
