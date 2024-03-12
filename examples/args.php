<?php

namespace args;

use Castor\Attribute\AsArgument;
use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\io;

/**
 * @param string[] $argument2
 */
#[AsTask(description: 'Dumps all arguments and options, with custom configuration')]
function args(
    #[AsArgument(description: 'This is a required argument without any typing', suggestedValues: ['hello', 'bonjour', 'hola'])]
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

#[AsTask(description: 'Dumps all arguments and options, without configuration')]
function another_args(
    string $required,
    int $test2 = 1
): void {
    io()->writeln($required . ' ' . $test2);
}

/**
 * @param string[] $rawTokens
 */
#[AsTask(description: 'Dumps all arguments and options, without configuration nor validation', ignoreValidationErrors: true)]
function passthru(#[AsRawTokens] array $rawTokens): void
{
    var_dump($rawTokens);
}
