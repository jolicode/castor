<?php

namespace watch;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\watch;

#[AsTask(description: 'Watches on filesystem changes')]
function filesystem(): void
{
    io()->writeln('Try editing a file');
    watch(\dirname(__DIR__) . '/...', function (string $name, string $type) {
        io()->writeln("File {$name} has been {$type}");
    });
}
