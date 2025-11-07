<?php

namespace arguments;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

/**
 * @param string[] $argument2
 */
#[AsTask(description: 'Dumps all arguments and options, with custom configuration')]
function arguments(
    #[AsArgument(description: 'This is a required argument without any typing', autocomplete: ['hello', 'bonjour', 'hola'])]
    $word,
    #[AsArgument(name: 'array-of-people', description: 'This is an optional array argument')]
    array $argument2 = ['world', 'PHP community'],
    #[AsOption(description: 'This with an option with an optional value')]
    string $option = 'default value',
    #[AsOption(description: 'This a an option without value in CLI')]
    bool $dryRun = false,
): void {
    var_dump(\func_get_args());
}
