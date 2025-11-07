# Executing Processes

## The `run()` function

Castor provides a `run()` function to execute external processes.

```php
{% include "/examples/basic/run/run.php" start="<?php\n\nnamespace run;\n\n" %}
```

You can pass a string or an array of string for this function. When passing a
string, arguments will not be escaped - use it carefully.

## Process object

Under the hood, Castor uses the
[`Symfony\Component\Process\Process`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Process/Process.php)
object to execute the process. The `run()` function will return this object. So
you can use the API of this class to interact with the underlying process:

```php
{% include "/examples/basic/run/ls.php" start="<?php\n\nnamespace run;\n\n" %}
```

> [!NOTE]
> Without the allowFailure option, Castor would throw an exception if the process
> execution failed. See [this documentation](context.md#failure) for more
> information about failure handling.

## Processing the output

By default, Castor will forward the stdout and stderr to the current terminal.
If you do not want to print the process output you can use a context with the
`quiet` option to true:

```php
{% include "/examples/basic/run/quiet.php" start="<?php\n\nnamespace run;\n\n" %}
```

### The `capture()` function

Castor provides a `capture()` function that will run the process quietly,
trims the output, then returns it:

```php
{% include "/examples/basic/run/capture.php" start="<?php\n\nnamespace run;\n\n" %}
```

### The `exit_code()` function

Castor provides a `exit_code()` function that will run the command, allowing
the process to fail and return its exit code. This is particularly useful when
running tasks on CI as this allows the CI to know if the task failed or not:

```php
{% include "/examples/basic/run/exit_code.php" start="<?php\n\nnamespace run;\n\n" %}
```

## Interactive Process

If you want to run an interactive process, you can transform any context into an interactive one:

```php
{% include "/examples/basic/run/interactive.php" start="<?php\n\nnamespace run;\n\n" %}
```

## PTY & TTY

By default, Castor will use a pseudo terminal (PTY) to run the underlying process,
which allows to have nice output in most cases.
For some commands you may want to disable the PTY and use a TTY instead. You can
do that by setting the `tty` option to `true`:

```php
{% include "/examples/basic/run/tty.php" start="<?php\n\nnamespace run;\n\n" %}
```

> [!WARNING]
> When using a TTY, the output of the command is empty in the process object
> (when using `getOutput()` or `getErrorOutput()`).

You can also disable the PTY by setting the `pty` option to `false`. If `pty`
and `tty` are both set to `false`, the standard input will not be forwarded to
the process:

```php
{% include "/examples/basic/run/pty.php" start="<?php\n\nnamespace run;\n\n" %}
```
