<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Console\Command\TaskCommand;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\Event\ProcessStartEvent;
use Castor\Event\ProcessTerminateEvent;
use Castor\Event\RegisterTaskEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;

use function Castor\io;
use function Castor\run;
use function Castor\task;

#[AsTask(description: 'An dummy task with event listeners attached')]
function my_task(): void
{
    run('echo "Hello from task!"');
}

#[AsTask(description: 'I should not be seen')]
function a_command_that_should_not_exist(): void
{
    run('echo "Hello from task!"');
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

#[AsListener(event: ConsoleEvents::TERMINATE)]
function console_terminate_event(ConsoleTerminateEvent $event): void
{
    if ('event-listener:my-task' === $event->getCommand()?->getName()) {
        io()->writeln('Hello from console terminate event listener!');
    }
}

#[AsListener(event: ProcessTerminateEvent::class)]
function process_terminate_event(ProcessTerminateEvent $event): void
{
    $command = task(true);

    if ('event-listener:my-task' !== $command?->getName()) {
        return;
    }

    io()->writeln('Hello after process stop!');
}

#[AsListener(event: ProcessStartEvent::class)]
function process_start_event(ProcessStartEvent $event): void
{
    $command = task(true);

    if ('event-listener:my-task' !== $command?->getName()) {
        return;
    }

    io()->writeln('Hello after process start!');
}

#[AsListener(event: RegisterTaskEvent::class)]
function process_register_task(RegisterTaskEvent $event): void
{
    if ($event->task instanceof TaskCommand) {
        $function = $event->task->function;

        if ('event_listener\\a_command_that_should_not_exist' === $function->getName()) {
            $event->register = false;
        }
    }
}
