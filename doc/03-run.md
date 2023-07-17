# The `run()` function

Castor provides a `Castor\run()` function to run commands. It allows to run a
process:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('echo "bar"');
    run(['echo', 'bar']);
}
```

You can pass a string or an array of string for this command. When passing a
string, arguments will not be escaped - use it carefully.

## Process object

Under the hood, Castor uses the
[`Symfony\Component\Process\Process`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Process/Process.php)
object to run the command. The `run()` function will return this object. So
you can use the API of this class to interact with the process:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    $process = run('echo "bar"');
    $process->isSuccessful(); // will return true
}
```

## Failure

By default, Castor will throw an exception if the command fails. You can disable
that by setting the `allowFailure` option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('a_command_that_does_not_exist', allowFailure: true);
}
```

## Working directory

By default, Castor will run the command in the same directory as
the `castor.php` file. You can change that by setting the `path` argument. It
can be either a relative or an absolute path:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('pwd', path: '../'); // run the command in the parent directory of the castor.php file
    run('pwd', path: '/tmp'); // run the command in the /tmp directory
}
```

## Environment variables

By default, Castor will use the same environment variables as the current
process. You can add or override environment variables by setting
the `environment` argument:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('echo $FOO', environment: ['FOO' => 'bar']); // will print "bar"
}
```

## Processing the output

By default, Castor will forward the stdout and stderr to the current terminal.
If you do not want to print the output of the command you can set the `quiet`
option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('echo "bar"', quiet: true); // will not print anything
}
```

You can also fetch the output of the command by using the API of
the `Symfony\Component\Process\Process` object:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    $process = run('echo "bar"', quiet: true); // will not print anything
    $output = $process->getOutput(); // will return "bar\n"
}
```

Castor also provides a `capture()` function that will run the command quietly,
trims the output, then returns it:

```php
use Castor\Attribute\AsTask;

use function Castor\capture;

#[AsTask()]
function whoami()
{
    $whoami = capture('whoami');

    echo "Hello: $whoami\n";
}
```

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

## PTY & TTY

By default, Castor will use a pseudo terminal (PTY) to run the command,
which allows to have nice output in most cases.
For some commands you may want to disable the PTY and use a TTY instead. You can
do that by setting the `tty` option to `true`:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('echo "bar"', tty: true);
}
```

> **Warning**
> When using a TTY, the output of the command is empty in the process object
> (when using `getOutput()` or `getErrorOutput()`).

You can also disable the pty by setting the `pty` option to `false`. If `pty`
and `tty` are both set to `false`, the standard input will not be forwarded to
the command:

```php
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask]
function foo(): void
{
    run('echo "bar"', pty: false);
}
```
