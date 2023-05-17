<?php

namespace Castor\Example;

use Castor\Attribute\Task;
use function Castor\{exec, cd};

#[Task(description: "A simple command that changes directory")]
function directory() {
    exec(['pwd']);
    cd('../');
    exec(['pwd']);
}