<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsTask;
use Castor\Context;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Castor\exec;

#[AsContext(default: true)]
function defaultContext(): Context
{
    return new Context([
        'production' => false,
        'foo' => 'bar',
    ]);
}

#[AsContext(name: 'production')]
function productionContext(): Context
{
    return defaultContext()
        ->withData([
            'production' => true,
        ])
    ;
}

#[AsContext(name: 'exec')]
function execContext(): Context
{
    $production = (bool) trim(exec('echo $PRODUCTION', quiet: true)->getOutput());
    $foo = trim(exec('echo $FOO', quiet: true)->getOutput()) ?: 'no defined';

    return new Context([
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
        'production' => (bool) $production,
        'foo' => $foo,
    ]);
}

#[AsTask(description: 'Displays information about the context')]
function context(Context $context)
{
    echo 'Production? ' . ($context['production'] ? 'yes' : 'no') . "\n";
    echo "verbosity: {$context->verbosityLevel->value}\n";
    echo "foo: {$context['foo']}\n";
}
