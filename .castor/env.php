<?php

namespace env;

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Castor\Context;

use function Castor\exec;

#[AsContext(name: 'context_env')]
function context_env(): Context
{
    return new Context(environment: [
        'FOO' => 'toto',
    ]);
}

#[Task(description: 'A simple task that use environment variables')]
function env()
{
    exec('echo foo: \"$FOO\", bar: \"$BAR\"', environment: ['BAR' => 'tata']);
}
