<?php

namespace Castor;

use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher = new SymfonyEventDispatcher(),
    ) {
    }

    public function dispatch(object $event, string $eventName = null): object
    {
        log("Dispatching event {$eventName}", 'debug', [
            'event' => $event,
        ]);

        return $this->eventDispatcher->dispatch($event, $eventName);
    }

    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        log("Adding listener for event {$eventName}", 'debug', [
            'listener' => $listener,
            'priority' => $priority,
        ]);

        $this->eventDispatcher->addListener($eventName, $listener, $priority);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        log("Removing listener for event {$eventName}", 'debug', [
            'listener' => $listener,
        ]);

        $this->eventDispatcher->removeListener($eventName, $listener);
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->addSubscriber($subscriber);
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        $this->eventDispatcher->removeSubscriber($subscriber);
    }

    public function getListeners(string $eventName = null): array
    {
        return $this->eventDispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->eventDispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(string $eventName = null): bool
    {
        return $this->eventDispatcher->hasListeners($eventName);
    }
}
