<?php

namespace Castor\Example;

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Castor\Context;
use function Castor\exec;

#[AsContext(name: 'context_env')]
function context_env(): Context {
    return new Context(environment: [
        'FOO' => 'toto',
    ]);
}

#[Task(description: "A simple task that use environment variables")]
function env(Context $context) {
    exec('echo $FOO $BAR', environment: ['BAR' => 'tata']);
}
