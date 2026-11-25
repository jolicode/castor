<?php

namespace CastorTest\Featured;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(namespace: 'featured', description: 'The "qa" feature of the package')]
function lint(): void
{
    io()->writeln('Linting from the featured package');
}
