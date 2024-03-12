<?php

namespace enabled;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Say hello, but only in production', enabled: "var('production') == true")]
function hello(): void
{
    io()->writeln('Hello world!');
}
