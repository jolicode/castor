<?php

namespace CastorTest\Featured;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(namespace: 'featured', description: 'Autoloaded by the package, its "default" feature')]
function hello(): void
{
    io()->writeln('Hello from the featured package');
}
