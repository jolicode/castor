<?php

namespace Castor\Example;

use Castor\Attribute\Task;
use function Castor\{capture};

#[Task(description: "A simple command that capture output", name: "capture")]
function captureFunction() {
    [$stdout, $stderr, $exitCode] = capture('echo -n test');

    echo "stdout: $stdout\n";
    echo "stderr: $stderr\n";
    echo "exitCode: $exitCode\n";
}