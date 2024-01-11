<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\add_context;
use function Castor\context;
use function Castor\io;
use function Castor\load_dot_env;
use function Castor\run;
use function Castor\variable;
use function Castor\with;

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
    $blankContext = new Context();
    $production = (bool) trim(run('echo $PRODUCTION', quiet: true, context: $blankContext)->getOutput());
    $foo = trim(run('echo $FOO', quiet: true, context: $blankContext)->getOutput()) ?: 'no defined';

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

#[AsContext(name: 'path')]
function contextFromPath(): Context
{
    /** @var array{name: string, production: bool} $data */
    $data = load_dot_env(__DIR__ . '/dotenv-context/.env');

    return new Context($data);
}

#[AsTask(description: 'Displays information about the context', name: 'context')]
function contextInfo(): void
{
    $context = context();
    echo 'context name: ' . variable('name', 'N/A') . "\n";
    echo 'Production? ' . (variable('production', false) ? 'yes' : 'no') . "\n";
    echo "verbosity: {$context->verbosityLevel->value}\n";
    echo 'context: ' . variable('foo', 'N/A') . "\n";
}

add_context('dynamic', fn () => new Context([
    'name' => 'dynamic',
    'production' => false,
    'foo' => 'baz',
]));

#[AsTask(description: 'Displays information about the context')]
function contextInfoForced(): void
{
    $context = context('dynamic');
    echo 'context name: ' . $context->data['name'] . "\n";
}

#[AsTask(description: 'Displays information about the context, using a specific context')]
function contextWith(): void
{
    $result = with(function (Context $context) {
        contextInfo();

        return $context->data['foo'] ?? 'N/A';
    }, data: ['foo' => 'bar'], context: 'dynamic');

    echo $result;
}
