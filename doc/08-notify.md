## Notification

Castor use the [JoliNotif](https://github.com/jolicode/jolinotif) library to display notifications. 
You can use the `notify` function to display a desktop notification.

```php
#[AskTask]
function notify()
{
    notify('Hello world!');
}
```

### Notify on exec

You can use the `notify` argument to the `exec` function to display a notification when a command has been executed.

```php
#[AskTask]
function notify()
{
    exec(['echo', 'notify'], notify: true); // will display a success notification
    exec('command_that_does_not_exist', notify: true); // will display a failure notification
}
```
