<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'A dummy task with many event listeners attached')]
function multiple(): void
{
    run('echo "Hello from task!"');
}

#[AsListener(event: BeforeExecuteTaskEvent::class)]
#[AsListener(event: AfterExecuteTaskEvent::class)]
function multiple_event_listener(BeforeExecuteTaskEvent|AfterExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:multiple' === $taskName && $event instanceof BeforeExecuteTaskEvent) {
        io()->writeln('Ola from listener! I am listening to multiple events but only showing only for BeforeExecuteTaskEvent');
    }

    if ('event-listener:multiple' === $taskName && $event instanceof AfterExecuteTaskEvent) {
        io()->writeln('Ola from listener! I am listening to multiple events but only showing only for AfterExecuteTaskEvent');
    }
}
