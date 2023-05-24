<?php

namespace rename;

use Castor\Attribute\AsTask;

#[AsTask(description: 'A simple task that was renamed', name: 'renamed', namespace: 'not-rename')]
function a_very_long_function_that_we_dont_want_to_write_on_command_line()
{
    echo "renamed\n";
}

#[AsTask(description: 'A simple task without a namespace', name: 'no-namespace', namespace: '')]
function no_namespace()
{
    echo "renamed\n";
}
