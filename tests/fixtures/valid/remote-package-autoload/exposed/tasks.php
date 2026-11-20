<?php

namespace CastorTest\Exposed;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(namespace: 'exposed', description: 'Exposed as soon as the package is required')]
function hello(): void
{
    io()->writeln('Hello from the exposed package');
}
