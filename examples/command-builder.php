<?php

namespace command_builder;

use Castor\Attribute\AsTask;

use function Castor\run;
use function ls\ls;

#[AsTask(name: 'ls', description: 'Run a sub-process and display information about it')]
function ls_task(): void
{
    run(ls(__DIR__ . '/command-builder')->all());
}
