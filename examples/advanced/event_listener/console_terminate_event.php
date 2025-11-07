<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

use function Castor\io;
use function Castor\run;

#[AsTask(description: 'A dummy task with console terminate event listener')]
function console_terminate(): void
{
    run('echo "Hello from task!"');
}

#[AsListener(event: ConsoleEvents::TERMINATE)]
function console_terminate_event_listener(ConsoleTerminateEvent $event): void
{
    if ('event-listener:console-terminate' === $event->getCommand()?->getName()) {
        io()->writeln('Hello from console terminate event listener!');
    }
}
