# Notification

Castor uses the [JoliNotif](https://github.com/jolicode/jolinotif) library to
display notifications.

## The `notify()` function

You can use the `notify()` function to display a desktop notification:

```php
use Castor\Attribute\AsTask;

use function Castor\notify;

#[AsTask]
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

#[AsTask]
function notify()
{
    run(['echo', 'notify'], notify: true); // will display a success notification
    run('command_that_does_not_exist', notify: true); // will display a failure notification
}
```
