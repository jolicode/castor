<?php

// Play with configuration and namespaces

namespace configuration\foo;

use Castor\Attribute\AsTask;
use Loggable;

use function Castor\io;

#[AsTask(description: 'Prints foo')]
#[Loggable]
function foo(): void
{
    io()->writeln('foo');
}

namespace configuration\bar;

use Castor\Attribute\AsTask;

use function Castor\io;
use function configuration\foo\foo;

#[AsTask(description: 'Prints bar, but also executes foo')]
function bar(): void
{
    foo();

    io()->writeln('bar');
}

namespace configuration\rename;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(name: 'renamed', namespace: 'configuration', description: 'Task that was renamed')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    io()->writeln('Foo bar');
}

#[AsTask(name: 'no-namespace', namespace: '', description: 'Task without a namespace')]
function no_namespace(): void
{
    io()->writeln('renamed');
}
