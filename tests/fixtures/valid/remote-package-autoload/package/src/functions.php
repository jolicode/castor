<?php

namespace CastorTest\Package;

use Castor\Attribute\AsTask;

use function Castor\io;

function greet(): string
{
    return 'Hello from the package';
}

#[AsTask(namespace: 'package', description: 'A task autoloaded by the package')]
function hello(): void
{
    io()->writeln(greet());
    io()->writeln('Working directory: ' . getcwd());
}
