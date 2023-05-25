## Notification

Castor uses the [JoliNotif](https://github.com/jolicode/jolinotif) library to
display notifications.
You can use the `notify` function to display a desktop notification:

```php
#[AsTask]
function notify()
{
    notify('Hello world!');
}
```

### Notify on exec

You can use the `notify` argument of the `exec` function to display a
notification when a command has been executed:

```php
#[AsTask]
function notify()
{
    exec(['echo', 'notify'], notify: true); // will display a success notification
    exec('command_that_does_not_exist', notify: true); // will display a failure notification
}
```
