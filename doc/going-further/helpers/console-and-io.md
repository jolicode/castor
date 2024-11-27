# Manipulating the input and output and interacting with the Console

Castor is heavily based on
[Symfony's Console component](https://symfony.com/doc/current/console.html).
This means that some powers of this component are available to you.

## The `io()` function

The `io()` returns an object that provides methods to interact with the user and
to display information. It returns an instance of
`Symfony\Component\Console\Style\SymfonyStyle`:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function foo(): void
{
    io()->title('This is a title');

    io()->comment('With IO, you can ask questions ...');
    $value = io()->ask('Tell me something');
    io()->writeln('You said: ' . $value);

    io()->comment('... show progress bars ...');
    io()->progressStart(100);
    for ($i = 0; $i < 100; ++$i) {
        io()->progressAdvance();
        usleep(1000);
    }
    io()->progressFinish();

    io()->comment('... show table ...');
    io()->table(['Name', 'Age'], [
        ['Alice', 21],
        ['Bob', 42],
    ]);

    io()->success('This is a success message');
}
```

> [!NOTE]
> You can check the
> [Symfony documentation](https://symfony.com/doc/current/console/style.html)
> for more information about this class and how to use it.

## The `input()` function

Castor provides the `input()` to access the current
[`Input`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Input/InputInterface.php)
object.

## The `output()` function

Castor provides the `output()` to access the current
[`Output`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Output/OutputInterface.php)
object.

## The `app()` function

Castor provides the `app()` to access the current
[`Application`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Application.php)
object.

## The `task()` function

Castor provides the `task(bool $allowNull = false)` to access the current
[`Symfony Command`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Command/Command.php)
object that powers the task currently run by the user.

> [!NOTE]
> The `task()` will reference the Castor task ran by the user, not the one where
> `task()` may be called.

Considering the example below:

```php
#[AsTask()]
function foo(): void
{
    io()->title(task()->getName());
}

#[AsTask()]
function bar(): void
{
    foo();
}
```

`castor bar` will output `bar`, not `foo`, even if this is the `foo()` function
that triggers the call to `task()`.

In some cases there may be no task to return, if an event listener is triggered
before the task  or during a context initialization for example. In this case,
`task()` will throw an exception. You can use the optional parameter to allow
`task(true)` to return `null` in this case.

## Experimental section output

When running commands in parallel, output can be mixed and hard to read. Castor
provides an experimental feature to display the output of each command in a
dedicated section of the console.

This feature is disabled by default and can be enabled by setting the
`CASTOR_USE_SECTION` environment variable to `true`.

```shell
CASTOR_USE_SECTION=true castor task-running-commands-in-parallel
```

> [!WARNING]
> This feature is experimental and may be removed or changed in the future.
