<?php

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

#[AsContext(default: true, name: 'foo')]
function foo_context(): Context
{
    return new Context();
}

#[AsTask]
function hello(): void
{
    echo \Castor\context()->name . "\n";
}
