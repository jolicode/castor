<?php

namespace Castor\Example;

use Castor\Attribute\Task;
use function Castor\exec;

#[Task(description: "This a task with arguments")]
function args(string $test, int $test2 = 1) {
    exec(["echo", $test, $test2]);
}