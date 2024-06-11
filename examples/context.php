<?php

namespace context;

use Castor\Attribute\AsContext;
use Castor\Attribute\AsContextGenerator;
use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Context;
use Castor\Event\ContextCreatedEvent;

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
        'nested' => [
            'merge' => [
                'key' => [
                    'value' => 'should keep this',
                    'replaced' => 'should be replaced',
                ],
                'another' => 'should keep',
            ],
            'another' => 'should keep',
        ],
    ]);
}

#[AsContext(name: 'production')]
function productionContext(): Context
{
    return defaultContext()
        ->withData(
            [
                'name' => 'production',
                'production' => true,
                'nested' => [
                    'merge' => [
                        'key' => [
                            'replaced' => 'replaced value',
                            'new' => 'new value',
                        ],
                    ],
                ],
            ],
            recursive: true
        )
    ;
}

#[AsContext(name: 'run')]
function runContext(): Context
{
    $blankContext = new Context();
    $production = (bool) trim(run('echo $PRODUCTION', context: $blankContext->withQuiet())->getOutput());
    $foo = trim(run('echo $FOO', context: $blankContext->withQuiet())->getOutput()) ?: 'no defined';

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
    io()->writeln('context name: ' . variable('name', 'N/A'));
    io()->writeln('Production? ' . (variable('production', false) ? 'yes' : 'no'));
    io()->writeln("verbosity: {$context->verbosityLevel->value}");
    io()->writeln('context: ' . variable('foo', 'N/A'));
    io()->writeln('nested merge recursive: ' . json_encode(variable('nested', []), \JSON_THROW_ON_ERROR));
}

/**
 * @return iterable<string, \Closure(): Context>
 */
#[AsContextGenerator()]
function context_generator(): iterable
{
    yield 'dynamic' => fn () => new Context([
        'name' => 'dynamic',
        'production' => false,
        'foo' => 'baz',
    ]);
}

#[AsTask(description: 'Displays information about the context')]
function contextInfoForced(): void
{
    $context = context('dynamic');
    io()->writeln('context name: ' . $context->data['name']);
}

#[AsTask(description: 'Displays information about the context, using a specific context')]
function contextWith(): void
{
    $result = with(function (Context $context) {
        contextInfo();

        return $context->data['foo'] ?? 'N/A';
    }, data: ['foo' => 'bar'], context: 'dynamic');

    io()->writeln($result);
}

#[AsContext(name: 'updated')]
function updatedContext(): Context
{
    return new Context();
}

#[AsListener(ContextCreatedEvent::class)]
function update_context(ContextCreatedEvent $event): void
{
    if ('updated' !== $event->contextName) {
        return;
    }

    $context = $event->context;
    $event->context = $context->withData(['name' => 'updated_context']);
}
