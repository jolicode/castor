<?php

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\FunctionsResolvedEvent;

use function Castor\io;
use function Castor\mount;

mount(__DIR__ . '/mounted');

#[AsTask(description: 'A task in the root application')]
function hello(): void
{
    io()->writeln('Hello from root');
}

#[AsListener(event: FunctionsResolvedEvent::class)]
function on_functions_resolved(FunctionsResolvedEvent $event): void
{
    io()->writeln(\sprintf(
        'FunctionsResolvedEvent dispatched (mountPath: %s, isRootMount: %s) with %d task(s)',
        basename($event->mountPath),
        $event->isRootMount ? 'true' : 'false',
        \count($event->taskDescriptors),
    ));
}
