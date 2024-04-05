<?php

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Castor\Event\ProcessCreatedEvent;

use function Castor\io;
use function Castor\watch;

#[AsTask()]
function fs_watch(): void
{
    io()->writeln('Watching for file change');
    watch(dirname(__DIR__) . '/...', function (string $name, string $type) {
        io()->writeln("File {$name} has been {$type}");
    });
}

#[AsListener(event: ProcessCreatedEvent::class)]
function process_created(ProcessCreatedEvent $event): void
{
    $event->process->setTimeout(1);
}
