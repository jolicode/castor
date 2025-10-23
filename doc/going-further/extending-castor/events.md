# Listening to events

Castor provides utilities to listen and react when event happened inside your
project. It allows to implement custom logic at various points in the
application lifecycle.

## Registering a listener

You can register a listener inside your Castor project by using the `Castor\Attribute\AsListener` attribute as a **function** (not a class method). This attribute allows you to specify the targeted event and the priority of this listener.

**Important:** Listener functions must be defined in a file that is imported in your `castor.php` file using the `import()` function.

### Example
```php
<?php
// file: src/EventListener/CastorListener.php

use Castor\Attribute\AsListener;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\ContextCreatedEvent;

#[AsListener(AfterExecuteTaskEvent::class)]
#[AsListener(ContextCreatedEvent::class)]
function my_event_listener(AfterExecuteTaskEvent|ContextCreatedEvent $event): void
{
    // Custom logic to handle the events
    echo "Event triggered: " . $event::class . "\n";
}

#[AsListener(ContextCreatedEvent::class, priority: 10)]
function high_priority_listener(ContextCreatedEvent $event): void
{
    // This listener will run before the one above due to higher priority
    echo "High priority listener\n";
}
```

Then import this file in your `castor.php`:
```php
<?php
// file: castor.php

use function Castor\import;

import('src/EventListener/CastorListener.php');

// ... rest of your tasks
```

**Note:** Use `Castor\Attribute\AsListener`, not Symfony's `AsEventListener` attribute.


> [!NOTE]
> You can specify multiple events for a single listener.

> [!NOTE]
> The `priority` argument is optional and defaults to `0`. The higher the
> priority, the earlier the listener will be executed.

## Built-in events

Here is the built-in events triggered by Castor:

* `Castor\Event\FunctionsResolvedEvent`: This event is triggered after the
  functions has been resolved. It provides access to an array of
  `TaskDescriptor` and `SymfonyTaskDescriptor` objects;

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

## Console events

Castor also provides a set of events related to the symfony console application,
which can be used to listen to the console lifecycle, see the [symfony documentation
to learn more about the console events](https://symfony.com/doc/current/components/console/events.html).
