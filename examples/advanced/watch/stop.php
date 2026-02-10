<?php

namespace watch;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\watch;

#[AsTask(description: 'Watches on filesystem changes and stop after first change')]
function stop(): void
{
    watch(\dirname(__DIR__) . '/...', static function (string $name, string $type) {
        io()->writeln("File {$name} has been {$type}");

        return false;
    });

    io()->writeln('Stop watching');
}
