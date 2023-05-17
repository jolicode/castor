<?php

namespace Castor\Example;

use Castor\Attribute\Task;

#[Task(description: "A simple command that prints foo")]
function foo() {
    echo "foo\n";
}
