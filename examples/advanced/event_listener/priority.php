<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\BeforeExecuteTaskEvent;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'A dummy task with event listeners that have priority')]
function priority(): void
{
    run('echo "Hello from task!"');
}

#[AsListener(event: BeforeExecuteTaskEvent::class, priority: -10)]
function higher_priority_listener(BeforeExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:priority' === $taskName) {
        io()->writeln('Hello from listener! (smaller priority) before task execution');
    }
}

#[AsListener(event: BeforeExecuteTaskEvent::class, priority: 10)]
function my_listener_that_has_higher_priority(BeforeExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:priority' === $taskName) {
        io()->writeln('Hello from listener! (higher priority) before task execution');
    }
}
