<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\BeforeExecuteTaskEvent;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'A dummy task with before execute task event listener')]
function before_execute_task(): void
{
    run('echo "Hello from task!"');
}

#[AsListener(event: BeforeExecuteTaskEvent::class)]
function before_execute_task_event_listener(BeforeExecuteTaskEvent $event): void
{
    $taskName = $event->task->getName();

    if ('event-listener:before-execute-task' === $taskName) {
        io()->writeln('Hello from listener!');
    }
}
