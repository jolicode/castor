<?php

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Completion\CompletionInput;

#[AsTask()]
function autocomplete_argument(
    #[AsArgument(name: 'argument', autocomplete: 'get_wrong_autocompletion')]
    string $argument,
): void {
}

function get_autocompletion(CompletionInput $input)
{
    return [
        'foo',
        'bar',
        'baz',
    ];
}
