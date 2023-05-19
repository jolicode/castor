<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\Task;
use Castor\Context;

use function Castor\exec;

#[AsContext(name: 'production')]
function productionContext(): Context
{
    return new Context(['production' => true]);
}

#[AsContext(default: true)]
function defaultContext(): Context
{
    return new Context(['production' => false]);
}

#[AsContext(name: 'exec')]
function execContext(): Context
{
    $production = trim(exec('echo $PRODUCTION')->getOutput());

    return new Context(['production' => (bool) $production]);
}

#[Task(description: 'A simple task that uses context')]
function context(Context $context)
{
    if ($context['production']) {
        echo "production\n";
    } else {
        echo "development\n";
    }
}
