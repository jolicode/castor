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
you need more information, you can re-run the command with the `-v` option.

## The `log()` function

You can use the `log()` function to log a message:

```php
use Castor\Attribute\AsTask;

use function Castor\log;

#[AsTask]
function log()
{
    log('Error!, this is an "error" log message.', 'error');
}
```

You can also attach a context to the log message:

```php
use Castor\Attribute\AsTask;

use function Castor\log;

#[AsTask]
function log()
{
    log('Hello, I\'have a context!', 'error', context: [
        'date' => new \DateTimeImmutable(),
    ]);
}
```

## Log something - the right way

You may wonder when to use the `log()` fonction, when to use `echo`, or when to
use the `OutputInterface`. Here is a small guide:

* Don't use `echo`, it's not a good practice;
* Use the `OutputInterface` when you want to display something to the user;
* Use the `log()` function when you want to add some **debug** information.

## Accessing the raw logger

If you need to access the raw logger instance, you can get it with the
`get_logger()` function:

```php
use Castor\Attribute\AsContext;
use Castor\Context;
use Castor\PathHelper;
use Monolog\Handler\StreamHandler;

use function Castor\get_logger;

#[AsContext(name: 'preprod')]
function preprodContext(): Context
{
    get_logger()->pushHandler(new StreamHandler(PathHelper::getRoot() . '/preprod.log'));

    //return new Context(...);
}
```
