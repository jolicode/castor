# Context

For every task that Castor run, it uses a `Context` object. This object
contains the default values for the `run` or `watch` function (directory,
environment variables, pty, tty, etc...).

It also contains custom values that can be set by the user and reused in
tasks.

The context is immutable, which means that every time you change a value, a new
context is created.

## Using the context

### The `context()` function

You can get the initial context thanks to the `context()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    $context = context();

    io()->writeln($context->workingDirectory); // will print the directory of the castor.php file

    $context = $context->withWorkingDirectory('/tmp'); // will create a new context where the current directory is /tmp
    run('pwd', context: $context); // will print "/tmp"
}
```

> [!TIP]
> Related example: [context.php](https://github.com/jolicode/castor/blob/main/examples/context.php)

### The `variable()` function

Castor also provides a `variable()` function to get the value of a variable
stored in the `Context`:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\variable;

#[AsTask()]
function foo(): void
{
    $foobar = variable('foobar', 'default value');

    // Same as:
    $context = context();
    try {
        $foobar = $context['foobar'];
    } catch (\OutOfBoundsException) {
        $foobar = 'default value;
    }
}
```

> [!TIP]
> Related example: [context.php](https://github.com/jolicode/castor/blob/main/examples/context.php)

## Creating a new context

You can create a new context by declaring a function with
the `Castor\Attribute\AsContext` attribute:

```php
use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\run;

#[AsContext()]
function my_context(): Context
{
    return new Context(environment: ['FOO' => 'BAR']);
}

#[AsTask()]
function foo(): void
{
    run('echo $FOO');
}
```

By default the `foo` task will not print anything as the `FOO` environment
variable is not set. If you want to use your new context you can use
the `--context` option:

```bash
$ castor foo

$ castor foo --context=my-context
BAR
```

> [!NOTE]
> You can override the context name by setting the `name` argument of the
> `AsContext` attribute.

> [!TIP]
> Related example: [context.php](https://github.com/jolicode/castor/blob/main/examples/context.php)

## Setting a default context

You may want to set a default context for all your tasks. You can do that by
setting the `default` argument to `true` in the `AsContext` attribute:

```php
use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\io;
use function Castor\run;

#[AsContext(default: true, name: 'my_context')]
function create_default_context(): Context
{
    return new Context(['foo' => 'bar'], context()->withWorkingDirectory('/tmp'));
}

#[AsTask()]
function foo(Context $context): void
{
    io()->writeln($context['foo']); // will print bar even if you do not use the --context option
    run('pwd'); // will print /tmp
}
```

> [!NOTE]
> You can also define the environment variable `CASTOR_CONTEXT` at runtime to
> override the default context to be used when no `--context` option is
> provided.

```bash
$ CASTOR_CONTEXT=another_context castor foo
```

## Failure

By default, Castor will throw an exception if the process fails. You can disable
that by using the `withAllowFailure` method:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('a_command_that_does_not_exist', context: context()->withAllowFailure());
}
```

> [!TIP]
> Related example: [failure.php](https://github.com/jolicode/castor/blob/main/examples/failure.php)

## Working directory

By default, Castor will execute the process in the same directory as
the `castor.php` file. You can change that by using the `withWorkingDirectory`
method. It can be either a relative or an absolute path:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('pwd', context: context()->withWorkingDirectory('../')); // run the process in the parent directory of the castor.php file
    run('pwd', context: context()->withWorkingDirectory('/tmp')); // run the process in the /tmp directory
}
```

> [!TIP]
> Related example: [cd.php](https://github.com/jolicode/castor/blob/main/examples/cd.php)

## Environment variables

By default, Castor will use the same environment variables as the current
process. You can add or override environment variables by using the 
`withEnvironment()` method:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo $FOO', context: context()->withEnvironment(['FOO' => 'bar'])); // will print "bar"
}
```

> [!TIP]
> Related example: [env.php](https://github.com/jolicode/castor/blob/main/examples/env.php)

## Timeout

By default, Castor allow your `run()` calls to go indefinitly.

If you want to tweak that you need to use the `withTimeout` method.

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('my-script.sh', context: context()->withTimeout(120));
}
```

This process will have a 2 minutes timeout.

> [!TIP]
> Related example: [wait_for.php](https://github.com/jolicode/castor/blob/main/examples/wait_for.php)

## PTY & TTY

By default, Castor will use a pseudo terminal (PTY) to run the underlying process,
which allows to have nice output in most cases.
For some commands you may want to disable the PTY and use a TTY instead. You can
do that by using the `withTty` method:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', context: context()->withTty());
}
```

> [!WARNING]
> When using a TTY, the output of the command is empty in the process object
> (when using `getOutput()` or `getErrorOutput()`).

You can also disable the pty by using the `withPty` method. If `withTty`
and `withPty` are both used with `false`, the standard input will not be forwarded to
the process:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('echo "bar"', context: context()->withPty(false)->withTty(false)); // print nothing
}
```

## Advanced usage

See [this documentation](../going-further/interacting-with-castor/advanced-context.md) for more usage about
contexts.
