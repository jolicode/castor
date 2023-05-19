<?php

use Castor\Attribute\Task;

#[Task(description: "A simple task that does not output anything")]
function quiet() {
    \Castor\exec('ls -alh', quiet: true);
}
