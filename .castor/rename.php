<?php

namespace Castor\Example;

use Castor\Attribute\Task;

#[Task(description: "A simple task that was renamed", name: "renamed", namespace: "not-rename")]
function a_very_long_function_that_we_dont_want_to_write_on_command_line() {
    echo "renamed\n";
}


#[Task(description: "A simple task that without a namespace", name: "no-namespace", namespace: "")]
function no_namespace() {
    echo "renamed\n";
}