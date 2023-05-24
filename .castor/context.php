<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\exec;

#[AsContext(name: 'production')]
function productionContext(): Context
{
    return defaultContext()->withData(['production' => true]);
}

#[AsContext(default: true)]
function defaultContext(): Context
{
    return new Context(['production' => false, 'foo' => 'bar']);
}

#[AsContext(name: 'exec')]
function execContext(): Context
{
    $production = trim(exec('echo $PRODUCTION', quiet: true)->getOutput());

    return new Context(['production' => (bool) $production]);
}

#[AsTask(description: 'A simple task that uses context')]
function context(Context $context)
{
    if ($context['production']) {
        echo "production\n";
    } else {
        echo "development\n";
    }

    echo "foo: {$context['foo']}\n";
}
