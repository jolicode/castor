<?php

namespace args;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\Input\InputOption;

use function Castor\run;

#[AsTask(description: 'Dumps all arguments and options, with custom configuration')]
function args(
    #[AsArgument(description: 'This is a required argument', suggestedValues: ['hello', 'bonjour', 'hola'])]
    $word,
    #[AsOption(description: 'This a an option without value in CLI', mode: InputOption::VALUE_NONE)]
    string $force,
    #[AsArgument(name: 'array-of-people', description: 'This is an optional array argument')]
    array $argument2 = ['world', 'PHP community'],
    #[AsOption(description: 'This with an option with an optional value')]
    string $option = 'default value',
) {
    var_dump(\func_get_args());
}

#[AsTask(description: 'Dumps all arguments and options, without configuration')]
function another_args(
    string $required,
    int $test2 = 1
) {
    run(['echo', $required, $test2]);
}
