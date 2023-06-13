<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\get_context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

#[AsContext(default: true, name: 'my_default')]
function defaultContext(): Context
{
    return new Context([
        'name' => 'my_default',
        'production' => false,
        'foo' => 'bar',
    ]);
}

#[AsContext(name: 'production')]
function productionContext(): Context
{
    return defaultContext()
        ->withData([
            'name' => 'production',
            'production' => true,
        ])
    ;
}

#[AsContext(name: 'run')]
function runContext(): Context
{
    $production = (bool) trim(run('echo $PRODUCTION', quiet: true)->getOutput());
    $foo = trim(run('echo $FOO', quiet: true)->getOutput()) ?: 'no defined';

    return new Context([
        'name' => 'run',
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}

#[AsContext(name: 'interactive')]
function interactiveContext(): Context
{
    $production = io()->confirm('Are you in production?', false);

    $foo = io()->ask('What is the "foo" value?', null);
    if (!\is_string($foo)) {
        throw new \RuntimeException('foo must be a string.');
    }

    return new Context([
        'name' => 'interactive',
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}

#[AsTask(description: 'Displays information about the context')]
function context(): void
{
    $context = get_context();
    echo 'context name: ' . variable('name', 'N/A') . "\n";
    echo 'Production? ' . (variable('production', false) ? 'yes' : 'no') . "\n";
    echo "verbosity: {$context->verbosityLevel->value}\n";
    echo 'context: ' . variable('foo', 'N/A') . "\n";
}
