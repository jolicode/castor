<?php

namespace event_listener;

use Castor\Attribute\AsListener;
use Castor\Attribute\AsTask;
use Symfony\Contracts\EventDispatcher\Event;

use function Castor\dispatch;
use function Castor\event_dispatcher;
use function Castor\io;

#[AsTask(description: 'Dispatch a custom event and collect data from its listeners')]
function dispatch_event(): void
{
    // A listener can also be registered at runtime through the event dispatcher.
    event_dispatcher()->addListener(BuildEvent::class, static function (BuildEvent $event): void {
        $event->steps[] = 'runtime listener';
    });

    // dispatch() returns the event, so we can read back what listeners added to it.
    $event = dispatch(new BuildEvent());

    io()->writeln('Collected steps: ' . implode(', ', $event->steps));
}

#[AsListener(event: BuildEvent::class)]
function collect_build_step(BuildEvent $event): void
{
    $event->steps[] = 'attribute listener';
}

// A custom event carrying a payload that listeners can enrich.
class BuildEvent extends Event
{
    /**
     * @param list<string> $steps
     */
    public function __construct(
        public array $steps = [],
    ) {
    }
}
