# Notification

Castor uses the [JoliNotif](https://github.com/jolicode/jolinotif) library to
display notifications.

## The `notify()` function

You can use the `notify()` function to display a desktop notification:

```php
use Castor\Attribute\AsTask;

use function Castor\notify;

#[AsTask()]
function notify()
{
    notify('Hello world!');
}
```

## Notify with `run()`

You can use the `notify` argument of the `run()` function to display a
notification when a command has been executed:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function notify()
{
    run(['echo', 'notify'], notify: true); // will display a success notification
    run('command_that_does_not_exist', notify: true); // will display a failure notification
}
```

## Customizing the notification title

You can set a custom title for notifications by setting the `notificationTitle` property in the context or
by passing a second argument to the `notify()` function:

> [!NOTE]
> By default the title is "Castor".
> The second argument of the `notify()` function will override the title set in the context.

```php
use Castor\Attribute\AsTask;

use function Castor\notify;
use Castor\Context;

#[AsContext(default: true)]
function my_context(): Context
{
    return new Context(
        notificationTitle: 'My custom title'
    );
}

#[AsTask()]
function notify()
{
    notify('Hello world!'); // will display a notification with the title "My custom title"
    notify('Hello world!', 'Specific title'); // will display a notification with the title "Specific title"
}
```
