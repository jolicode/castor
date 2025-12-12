<?php

namespace io;

use Castor\Attribute\AsTask;

use function Castor\io;
use function Castor\terminal;

#[AsTask(description: 'Get terminal properties')]
function term(): void
{
    io()->writeln('Current terminal width: ' . terminal()->getWidth());
    io()->writeln('Current terminal height: ' . terminal()->getHeight());
}
