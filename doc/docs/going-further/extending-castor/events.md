---
description: >
  Learn how to listen to and react to events in Castor by registering
  listeners, and explore the built-in events for advanced customization of your
  tasks.
---

# Listening to events

Castor provides utilities to listen and react when events happen inside your
project, allowing custom logic at various points in the application lifecycle.

## Registering a listener

You can register a listener inside your Castor project by using the
`#[Castor\Attribute\AsListener()]` attribute. This attribute allows you to specify
the targeted event and the priority of this listener.

```php
use Castor\Attribute\AsListener;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\FunctionsResolvedEvent;

#[AsListener(event: AfterExecuteTaskEvent::class)]
#[AsListener(event: FunctionsResolvedEvent::class, priority: 1)]
function my_event_listener(AfterExecuteTaskEvent|FunctionsResolvedEvent $event): void
{
    // Custom logic to handle the events
}
```

> [!NOTE]
> You can specify multiple events for a single listener.
<!-- -->
> [!NOTE]
> The `priority` argument is optional and defaults to `0`. The higher the
> priority, the earlier the listener will be executed.

## Dispatching events

You can dispatch your own events, which is especially useful when writing a
Castor extension that wants to let other projects hook into its lifecycle.

Use the `Castor\dispatch()` function to dispatch an event. It returns the same
event instance, so you can read back any data mutated by the listeners:

```php
use function Castor\dispatch;

$event = dispatch(new MyCustomEvent('some payload'));
```

Any listener registered with `#[Castor\Attribute\AsListener()]` for that event
class will be called.

If you need finer control over the dispatcher, for instance to register or
remove listeners dynamically or to introspect the registered listeners, you can
retrieve the dispatcher itself with the `Castor\event_dispatcher()` function:

```php
use function Castor\event_dispatcher;

event_dispatcher()->addListener(MyCustomEvent::class, function (MyCustomEvent $event): void {
    // Custom logic to handle the event
});
```

It returns a `Symfony\Component\EventDispatcher\EventDispatcherInterface`
instance.

## Built-in events

Here is the built-in events triggered by Castor:

* `Castor\Event\FunctionsResolvedEvent`: This event is triggered after the
  functions has been resolved. It provides access to an array of
  `TaskDescriptor` and `SymfonyTaskDescriptor` objects. It also exposes the
  `mountPath` (the path of the mount being resolved) and `isRootMount` (whether
  it is the root application or a mounted one) properties. See
  [Functions resolved and mounts](#functions-resolved-and-mounts) below;

* `Castor\Event\AfterBootEvent`: This event is triggered when the application is
  ready to execute task

* `Castor\Event\BeforeExecuteTaskEvent`: This event is triggered before
  executing a task. It provides access to the `TaskCommand` instance;

* `Castor\Event\AfterExecuteTaskEvent`: This event is triggered after executing
  a task. It provides access to the `TaskCommand` instance.

* `Castor\Event\ProcessCreatedEvent`: This event is triggered after a process
  has been created by the `run` function but not yet started. It provides access
  to the `Process` instance.

* `Castor\Event\ProcessStartEvent`: This event is triggered after a process has
  been started by the `run` function. It provides access to the `Process`
  instance.

* `Castor\Event\ProcessTerminateEvent`: This event is triggered after a process
  has been terminated and launched inside the `run` function. It provides access
  to the `Process` instance.

* `Castor\Event\ContextCreatedEvent`: This event is triggered after a context
  has been created. It allows to update the `Context` that will be used by the
  application.

## Functions resolved and mounts

The `FunctionsResolvedEvent` is dispatched **once per mount**, not once per
application. When you [mount another application](mount.md), Castor resolves the
functions of the root application and then of each mounted application
separately, dispatching a `FunctionsResolvedEvent` for each of them.

This means a listener attached to `FunctionsResolvedEvent` runs several times
when mounts are involved: once for the root application and once for every
mount. Each dispatch only carries the `TaskDescriptor` and
`SymfonyTaskDescriptor` objects resolved for that specific mount, so a listener
that performs a global, one-time side effect could run more than once.

To only act once, for the root application, guard your listener with the
`isRootMount` property. The `mountPath` property gives you the filesystem path
of the mount currently being resolved:

```php
use Castor\Attribute\AsListener;
use Castor\Event\FunctionsResolvedEvent;

#[AsListener(event: FunctionsResolvedEvent::class)]
function on_functions_resolved(FunctionsResolvedEvent $event): void
{
    if (!$event->isRootMount) {
        // Skip mounted applications, only run for the root application
        return;
    }

    // Custom logic that must run only once
}
```

## Console events

Castor also provides a set of events related to the symfony console application,
which can be used to listen to the console lifecycle, see the [symfony documentation
to learn more about the console events](https://symfony.com/doc/current/components/console/events.html).
