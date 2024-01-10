<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;

use function Castor\io;

#[AsTask(description: 'An dummy task with event listeners attached')]
function my_task(): void
{
    io()->writeln('Hello from task!');
}

#[AsListener(event: BeforeExecuteTaskEvent::class, priority: 1)]
function my_listener(BeforeExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:my-task' === $taskName) {
        io()->writeln('Hello from listener! (lower priority)');
    }
}

#[AsListener(event: BeforeExecuteTaskEvent::class, priority: 10)]
function my_listener_that_has_higher_priority(BeforeExecuteTaskEvent|AfterExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:my-task' === $taskName) {
        io()->writeln('Hello from listener! (higher priority) before task execution');
    }
}

#[AsListener(event: BeforeExecuteTaskEvent::class)]
#[AsListener(event: AfterExecuteTaskEvent::class)]
function my_listener_that_has_higher_priority_for_multiple_events(BeforeExecuteTaskEvent|AfterExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:my-task' === $taskName && $event instanceof BeforeExecuteTaskEvent) {
        io()->writeln('Ola from listener! I am listening to multiple events but only showing only for BeforeExecuteTaskEvent');
    }

    if ('event-listener:my-task' === $taskName && $event instanceof AfterExecuteTaskEvent) {
        io()->writeln('Ola from listener! I am listening to multiple events but only showing only for AfterExecuteTaskEvent');
    }
}
