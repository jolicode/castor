<?php

namespace args;

use Castor\Attribute\AsArg;
use Castor\Attribute\AsTask;

use function Castor\exec;

#[AsTask(description: 'This a task with arguments')]
function args(
    #[AsArg(description: 'This is a required argument')] string $required,
    #[AsArg(name: 'optional', description: 'This is an optional test argument')] int $test2 = 1,
    string|null $itsAlsoOptional = null
) {
    exec(['echo', $required, $test2, $itsAlsoOptional]);
}

#[AsTask(description: 'This a another task with arguments, work without attributes')]
function another_args(
    string $required,
    int $test2 = 1
) {
    exec(['echo', $required, $test2]);
}
