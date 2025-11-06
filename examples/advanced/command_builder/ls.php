<?php

namespace command_builder;

use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask(name: 'ls', description: 'Run a sub-process and display information about it')]
function ls_(): void
{
    run(ls(__DIR__)->all()->sortBySize());
}
