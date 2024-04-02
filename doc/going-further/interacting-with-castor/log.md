# Log and Debug

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
you need more information, you can re-run the task with the `-v` option.

## The `log()` function

You can use the `log()` function to log a message:

```php
use Castor\Attribute\AsTask;

use function Castor\log;

#[AsTask()]
function log()
{
    log('Error!, this is an "error" log message.', 'error');
}
```

You can also attach a context to the log message:

```php
use Castor\Attribute\AsTask;

use function Castor\log;

#[AsTask()]
function log()
{
    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}
```

## Log something - the right way

You may wonder when to use the `log()` or `io()` functions or even `echo` to
output something. Here is a small guide:

* Don't use PHP's native `echo` instruction, it's not a good practice;
* Use the [`io()` function](../helpers/console-and-io.md#the-io-function) to display
something to the user thanks to Symfony's `SymfonyStyle` class;
* Use the `log()` function when you want to add some **debug** information.

## The `logger()` function

If you need to access the raw logger instance, you can get it with the
`logger()` function:

```php
use Castor\Attribute\AsContext;
use Castor\Context;
use Castor\Helper\PathHelper;
use Monolog\Handler\StreamHandler;

use function Castor\logger;

#[AsContext(name: 'preprod')]
function preprodContext(): Context
{
    logger()->pushHandler(new StreamHandler(PathHelper::getRoot() . '/preprod.log'));

    //return new Context(...);
}
```

## The `debug` task

Castor ships a `debug` task that displays the current context, the root
directory, the cache directory, and more information. Run with:

```console
castor debug
```

If you want to define your own `debug` command, you can still access to the
castor task with:

```
castor castor:debug
```
