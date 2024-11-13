<?php

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(default: true)]
function about(): void
{
    io()->writeln('about');
}
