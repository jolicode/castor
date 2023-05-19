<?php

use Castor\Attribute\Task;

use function Castor\parallel;
use function Castor\watch;

#[Task(description: 'A simple task that watch on filesystem changes')]
function fs_change()
{
    watch(dirname(__DIR__) . '/...', function ($name, $type) {
        echo "File {$name} has been {$type}\n";
    });
}

#[Task(description: 'A simple task that watch on filesystem changes')]
function parralel()
{
    parallel(
        function () {
            watch(dirname(__DIR__) . '/...', function ($name, $type) {
                echo "First: File {$name} has been {$type}\n";
            });
        },
        function () {
            watch(dirname(__DIR__) . '/...', function ($name, $type) {
                echo "Second : File {$name} has been {$type}\n";
            });
        },
    );
}
