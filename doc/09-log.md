## Log

Castor has logging capabilities. It relies on
[Monolog](https://github.com/seldaek/monolog) and is also configured with
[Symfony Console](https://symfony.com/doc/current/logging/monolog_console.html).

There are different log levels, and you can control the log level displayed with
the `-v` option:

```
castor      # display level "warning" and above
castor -v   # display level "notice" and above
castor -vv  # display level "info" and above
castor -vvv # display level "debug" and above
```

When an error occurs, the error message is displayed and the program exits. If
you need more information, you can re-run the command with the `-v` option.

### The `log()` function

You can use the `log()` function to log a message:

```php
#[AsTask]
function log()
{
    log('Error!, this is an "error" log message.', 'error');
}
```

You can also attach a context to the log message:

```php
#[AsTask]
function log()
{
    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}
```