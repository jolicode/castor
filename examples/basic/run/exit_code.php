<?php

namespace run;

use Castor\Attribute\AsTask;

use function Castor\exit_code;

#[AsTask(description: 'Run a sub-process and return its exit code, with exit_code() function')]
function exit_code_(): int
{
    return exit_code('test -f unknown-file');
}
