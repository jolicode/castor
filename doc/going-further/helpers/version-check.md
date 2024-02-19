# Guard to check the version of castor

## The `guard_min_version()` function

```php
use function Castor\guard_min_version;

guard_min_version('v0.11.0');
```

This function will throw an exception if the current version of Castor is lower (e.g. `0.10.0`).
That will force the user to update Castor before running the command.

This is useful when you want to use a new feature of Castor in your command. And you want to
ensure that the user has the right version of Castor.

> [!NOTE]
> Where to put this function? 
> 
> It depends on your usage. If you want to ensure that the user
> has the right version of Castor before running any task, you can put it in the top of
> your `castor.php` file. 
> 
> If you want to ensure that the user has the right version of Castor
> before running a specific task, you can put it in the task function directly and 
> check will be done only when the task is called.

You can go further with [Events and Listeners](../extending-castor/events.md#listening-to-events) to check certain conditions by checking a pattern task name.
