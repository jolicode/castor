# Event Listening in PHP

Implementation for event listening using custom events and listeners. The primary purpose is to allow custom logic to be executed at various points in the application lifecycle.

## Table of Contents

- [Listener Implementation](#listener-implementation)
- [Provided Events](#provided-events)

---

## Listener Implementation

The listener is implemented using the `AsListener` attribute to specify the event it listens to and the priority.

```php
#[AsListener(event: AfterApplicationInitializationEvent::class, priority: 1)]
#[AsListener(event: AfterExecuteTaskEvent::class, priority: 1)] // Multiple events can be specified
function my_event_listener(AfterApplicationInitializationEvent|AfterExecuteTaskEvent $event): void
{
    // Custom logic to handle the events
}
```

---

## Provided Events (ordered by dispatch priority)

### 1. `Castor\Event\AfterApplicationInitializationEvent`

This event is triggered after the application has been initialized. It provides access to the `Application` instance and an array of `TaskDescriptor` objects.

### 2. `Castor\Event\BeforeExecuteTaskEvent`

This event is triggered before executing a task. It provides access to the `TaskCommand` instance, `AsTask` attribute, and the reflection function.

### 3. `Castor\Event\AfterExecuteTaskEvent`

This event is triggered after executing a task. It provides access to the `TaskCommand` instance, `AsTask` attribute, reflection function, and the result of the execution.
