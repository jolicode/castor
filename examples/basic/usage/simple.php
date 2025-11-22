<?php

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function hello(): void
{
    io()->writeln('Hello from castor.');
}
