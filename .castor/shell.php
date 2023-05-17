<?php

use Castor\Attribute\Task;

#[Task(description: "A simple task that run a bash")]
function bash() {
    \Castor\exec('bash', tty: true);
}