<?php

use Castor\Attribute\Task;

use function Castor\watch;

#[Task(description: 'A simple task that watch on filesystem changes')]
function fs_change(string $path = __DIR__ . '/..')
{
    watch($path, function (string $file, string $operation) {
        echo "Filesystem changed detected in \"{$file}\", operation: \"{$operation}\"\n";
    });
}
