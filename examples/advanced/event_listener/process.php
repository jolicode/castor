<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\ProcessCreatedEvent;
use Castor\Event\ProcessStartEvent;
use Castor\Event\ProcessTerminateEvent;

use function Castor\io;
use function Castor\run;
use function Castor\task;

#[AsTask(description: 'A dummy task with process event listeners')]
function process(): void
{
    run('echo "Hello from process"');
}

#[AsListener(event: ProcessTerminateEvent::class)]
function process_terminate_event(ProcessTerminateEvent $event): void
{
    $command = task(true);

    if ('event-listener:process' !== $command?->getName()) {
        return;
    }

    io()->writeln('Hello after process stop');
}

#[AsListener(event: ProcessStartEvent::class)]
function process_start_event(ProcessStartEvent $event): void
{
    $command = task(true);

    if ('event-listener:process' !== $command?->getName()) {
        return;
    }

    io()->writeln('Hello after process start');
}

#[AsListener(event: ProcessCreatedEvent::class)]
function process_created_event(ProcessCreatedEvent $event): void
{
    $command = task(true);

    if ('event-listener:process' !== $command?->getName()) {
        return;
    }

    io()->writeln('Hello after process creation');
}
