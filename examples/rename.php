<?php

namespace rename;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Task that was renamed', name: 'renamed', namespace: 'not-rename')]
function a_very_long_function_that_we_dont_want_to_write_on_command_line(): void
{
    echo "renamed\n";
}

#[AsTask(description: 'Task without a namespace', name: 'no-namespace', namespace: '')]
function no_namespace(): void
{
    echo "renamed\n";
}
