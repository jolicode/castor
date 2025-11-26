<?php

namespace Usage\With\Long\Namespace;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function hello(): void
{
    io()->writeln('Hello from Castor.');
}
