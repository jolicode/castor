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

## Failure

By default, Castor will throw an exception if the process fails. You can disable
that by setting the `allowFailure` option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('a_command_that_does_not_exist', allowFailure: true);
}
```

> [!TIP]
> Related example: [failure.php](https://github.com/jolicode/castor/blob/main/examples/failure.php)

## Working directory

By default, Castor will execute the process in the same directory as
the `castor.php` file. You can change that by setting the `workingDirectory`
argument. It can be either a relative or an absolute path:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('pwd', workingDirectory: '../'); // run the process in the parent directory of the castor.php file
    run('pwd', workingDirectory: '/tmp'); // run the process in the /tmp directory
}
```

> [!TIP]
> Related example: [cd.php](https://github.com/jolicode/castor/blob/main/examples/cd.php)

## Environment variables

By default, Castor will use the same environment variables as the current
process. You can add or override environment variables by setting
the `environment` argument:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo $FOO', environment: ['FOO' => 'bar']); // will print "bar"
}
```

> [!TIP]
> Related example: [env.php](https://github.com/jolicode/castor/blob/main/examples/env.php)

## Processing the output

By default, Castor will forward the stdout and stderr to the current terminal.
If you do not want to print the process output you can set the `quiet`
option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', quiet: true); // will not print anything
}
```

You can also fetch the process output by using the 
returned `Symfony\Component\Process\Process` object:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    $process = run('echo "bar"', quiet: true); // will not print anything
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

## Timeout

By default, Castor allow your `run()` calls to go indefinitly.

If you want to tweak that you need to set the `timeout` argument.

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('my-script.sh', timeout: 120);
}
```

This process will have a 2 minutes timeout.

> [!TIP]
> Related example: [wait_for.php](https://github.com/jolicode/castor/blob/main/examples/wait_for.php)

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
