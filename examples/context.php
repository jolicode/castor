<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\run;

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
function interactiveContext(SymfonyStyle $io): Context
{
    $production = $io->confirm('Are you in production?', 'no');
    $foo = $io->ask('What is the "foo" value?', null);

    return new Context([
        'name' => 'interactive',
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}

#[AsTask(description: 'Displays information about the context')]
function context(Context $context)
{
    echo 'context name: ' . ($context->offsetExists('name') ? $context['name'] : 'N/A') . "\n";
    echo 'Production? ' . ($context->offsetExists('production') && $context['production'] ? 'yes' : 'no') . "\n";
    echo "verbosity: {$context->verbosityLevel->value}\n";
    echo 'context: ' . ($context->offsetExists('foo') ? $context['foo'] : 'N/A') . "\n";
}
