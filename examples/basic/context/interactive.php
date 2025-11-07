<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\io;

#[AsContext(name: 'interactive')]
function interactiveContext(): Context
{
    $production = io()->confirm('Are you in production?', false);

    $foo = io()->ask('What is the "foo" value?', null);

    return new Context([
        'name' => 'interactive',
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}
