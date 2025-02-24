<?php

namespace Castor;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\EventDispatcher\EventDispatcher as SymfonyEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

#[AsDecorator(EventDispatcherInterface::class)]
class EventDispatcher implements EventDispatcherInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher = new SymfonyEventDispatcher(),
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->logger->debug("Dispatching event {$eventName}", [
            'event' => $event,
        ]);

        return $this->eventDispatcher->dispatch($event, $eventName);
    }

    /**
     * @param callable $listener
     */
    public function addListener(string $eventName, $listener, int $priority = 0): void
    {
        $this->logger->debug("Adding listener for event {$eventName}", [
            'priority' => $priority,
            'listener' => $listener,
        ]);

        $this->eventDispatcher->addListener($eventName, $listener, $priority);
    }

    public function removeListener(string $eventName, callable $listener): void
    {
        $this->logger->debug("Removing listener for event {$eventName}", [
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

    public function getListeners(?string $eventName = null): array
    {
        return $this->eventDispatcher->getListeners($eventName);
    }

    public function getListenerPriority(string $eventName, callable $listener): ?int
    {
        return $this->eventDispatcher->getListenerPriority($eventName, $listener);
    }

    public function hasListeners(?string $eventName = null): bool
    {
        return $this->eventDispatcher->hasListeners($eventName);
    }
}
