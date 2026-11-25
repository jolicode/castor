<?php

namespace CastorTest\Featured;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(namespace: 'featured', description: 'The "tools" feature of the package')]
function format(): void
{
    io()->writeln('Formatting from the featured package');
}
