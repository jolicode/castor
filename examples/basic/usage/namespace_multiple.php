<?php

namespace foo1;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function hello1(): void
{
    io()->writeln('Foo1');
}

namespace foo2;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function hello2(): void
{
    io()->writeln('Foo2');
}
