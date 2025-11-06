<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\BeforeExecuteTaskEvent;

use function Castor\io;

#[AsTask(description: 'A dummy task to demonstrate accessing attributes of a task')]
#[Loggable]
function loggable(): void
{
    io()->writeln('foo');
}

#[\Attribute(\Attribute::TARGET_FUNCTION)]
class Loggable
{
}

#[AsListener(event: BeforeExecuteTaskEvent::class)]
function access_attributes_of_task(BeforeExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    $isLoggable = [] !== $event->task->getAttributes(Loggable::class);

    if ($isLoggable) {
        io()->writeln("Task {$taskName} is loggable");
    }
}
