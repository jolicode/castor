<?php

namespace watch;

use Castor\Attribute\AsTask;

use function Castor\parallel;
use function Castor\watch;

#[AsTask(description: 'A simple task that watches on filesystem changes')]
function fs_change()
{
    watch(\dirname(__DIR__) . '/...', function ($name, $type) {
        echo "File {$name} has been {$type}\n";
    });
}

#[AsTask(description: 'A simple task that watches on filesystem changes and stop after first change')]
function stop()
{
    watch(\dirname(__DIR__) . '/...', function ($name, $type) {
        echo "File {$name} has been {$type}\n";

        return false;
    });
    echo "Stop watching\n";
}

#[AsTask(description: 'A simple task that watches on filesystem changes with 2 watchers in parallel')]
function parallel_change()
{
    parallel(
        function () {
            watch(\dirname(__DIR__) . '/...', function ($name, $type) {
                echo "First: File {$name} has been {$type}\n";
            });
        },
        function () {
            watch(\dirname(__DIR__) . '/...', function ($name, $type) {
                echo "Second : File {$name} has been {$type}\n";
            });
        },
    );
}
