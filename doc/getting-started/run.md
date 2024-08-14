# Executing Processes

## The `run()` function

Castor provides a `run()` function to execute external processes.

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('my-script.sh');
    run(['php', 'vendor/bin/phpunit', '--filter', 'MyTest']);
}
```

You can pass a string or an array of string for this function. When passing a
string, arguments will not be escaped - use it carefully.

## Process object

Under the hood, Castor uses the
[`Symfony\Component\Process\Process`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Process/Process.php)
object to execute the process. The `run()` function will return this object. So
you can use the API of this class to interact with the underlying process:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    $process = run('my-script.sh');
    $process->isSuccessful(); // will return true if the process exited with code 0.
}
```

> [!TIP]
> Related example: [run.php](https://github.com/jolicode/castor/blob/main/examples/run.php)

## Processing the output

By default, Castor will forward the stdout and stderr to the current terminal.
If you do not want to print the process output you can use a context with the
`quiet` option to true:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', context: context()->withQuiet()); // will not print anything
}
```

You can also fetch the process output by using the
returned `Symfony\Component\Process\Process` object:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    $process = run('echo "bar"', context: context()->withQuiet())); // will not print anything
    $output = $process->getOutput(); // will return "bar\n"
}
```

> [!TIP]
> Related example: [quiet.php](https://github.com/jolicode/castor/blob/main/examples/quiet.php)

### The `capture()` function

Castor provides a `capture()` function that will run the process quietly,
trims the output, then returns it:

```php
use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\io;

#[AsTask()]
function whoami()
{
    $whoami = capture('whoami');

    io()->writeln("Hello: $whoami");
}
```

> [!TIP]
> Related example: [run.php](https://github.com/jolicode/castor/blob/main/examples/run.php)

### The `exit_code()` function

Castor provides a `exit_code()` function that will run the command, allowing
the process to fail and return its exit code. This is particularly useful when
running tasks on CI as this allows the CI to know if the task failed or not:

```php
use Castor\Attribute\AsTask;

use function Castor\exit_code;

#[AsTask()]
function cs(): int
{
    return exit_code('php-cs-fixer fix --dry-run');
}
```

> [!TIP]
> Related example: [run.php](https://github.com/jolicode/castor/blob/main/examples/run.php)

## Interactive Process

If you want to run an interactive process, you can transform any context into an interactive one:

```php
use Castor\Attribute\AsTask;

use function Castor\run;
use function Castor\context;

#[AsTask()]
function vim(): void
{
    run('vim', context: context()->toInteractive());
}
```

## PTY & TTY

By default, Castor will use a pseudo terminal (PTY) to run the underlying process,
which allows to have nice output in most cases.
For some commands you may want to disable the PTY and use a TTY instead. You can
do that by setting the `tty` option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', tty: true);
}
```

> [!WARNING]
> When using a TTY, the output of the command is empty in the process object
> (when using `getOutput()` or `getErrorOutput()`).

You can also disable the pty by setting the `pty` option to `false`. If `pty`
and `tty` are both set to `false`, the standard input will not be forwarded to
the process:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', pty: false);
}
```
