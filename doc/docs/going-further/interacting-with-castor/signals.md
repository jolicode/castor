---
description: >
  Learn how to handle signals in Castor to gracefully manage task execution,
  such as stopping a task with CTRL+C or responding to other system signals.
---

# Handling signals

Castor can handle signals sent to the process. This is useful to gracefully
stop a task when the user presses `CTRL+C` or to handle other signals:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(onSignals: [\SIGUSR2 => 'onSigUsr2'])]
function foo(): void
{
    // Do something...
}

function onSigUsr2(int $signal): int|false
{
    io()->writeln("SIGUSR2 received\n");

    return false;
}
```

Return false to continue the task, or return an integer to stop the task
with this exit code.

If the task is in a namespace, you must use the fully qualified name of the function:

```php
namespace signal;

use Castor\Attribute\AsTask;

#[AsTask(onSignals: [\SIGUSR2 => 'signal\onSigUsr2'])]
function foo(): void
{
    // Do something...
}

function onSigUsr2(int $signal): int|false
{
    io()->writeln('SIGUSR2 received');

    return false;
}
```

## Interrupting a task

By default, a signal sent to Castor - like the `SIGINT` triggered by a `CTRL+C` -
interrupts Castor itself, and the task is not executed any further. Castor then
exits with the code 128 + the number of the signal, like a shell: `130` for a
`SIGINT`, `143` for a `SIGTERM`. So a `CTRL+C` also stops
`castor build && castor deploy`.

A process that has the terminal - run with [`withTty()`](../../getting-started/context.md#pty-tty)
or `toInteractive()` - may put it in raw mode, like `docker run -it` or `vim`
do. The `CTRL+C` then reaches this process only, and not Castor: the process
exits with the code `130`. Castor stops the task in this case too, even when the
failures are allowed (with `withAllowFailure()`, `exit_code()`,
`toInteractive()`, ...): it shows a short warning, and exits with the code
`130`. The same goes for a process killed by a `SIGINT` that Castor did not
receive.

Without the terminal, Castor receives the `CTRL+C` itself: the exit code `130`
of a process is then a failure like any other.

> [!NOTE]
> An interactive shell run with the terminal exits with the code of its last
> command: `130` when this command has been interrupted, or when the prompt has
> been cleared with a `CTRL+C`. To let the task go on after it, trap `SIGINT`
> as explained below.

## Trapping signals sent to Castor

If you want to stop only the process currently running, and let the task
continue, you can ask the context to trap the signals with the
`withTrappedSignals()` method. When Castor receives one of these signals while
running a process, it forwards it to that process instead of being interrupted.
And when `SIGINT` is trapped, a process interrupted on its own does not stop the
task either:

```php
{% include "/examples/advanced/signal/trap.php" start="<?php\n\nnamespace signal;\n\n" %}
```

Without any argument, `withTrappedSignals()` traps `SIGINT` and `SIGTERM`. You
can trap other signals by passing the list you want:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foo(): void
{
    run('./my-server', context: context()->withTrappedSignals([\SIGINT, \SIGQUIT]));
}
```

`SIGKILL` and `SIGSTOP` cannot be caught by a process: passing one of them to
`withTrappedSignals()` throws an `InvalidArgumentException`.

Since the process is stopped by a signal, it exits with a non-zero exit code
(`130` for a `SIGINT`, `143` for a `SIGTERM`, ...). As for any other failure,
Castor throws a `ProcessFailedException`, unless you use the
[`withAllowFailure()`](../../getting-started/context.md#failure) method.

As the trap is only installed while a process is running, a signal received
between two `run()` calls keeps its usual behavior and stops Castor.

Signals are forwarded to the process started by Castor only, not to the
processes this one may have started itself. Note also that a `CTRL+C` is sent by
the terminal to every process of the foreground process group, so the process
usually receives it directly too.

### Combining a trap with the `onSignals` handlers

A trapped signal is consumed by Castor: while the process is running, the
`onSignals` handler of the task is not called for this signal, as the signal is
meant for the process. Once the process is finished, the trap is released and
the handler of the task gets the signals again:

```php
{% include "/examples/advanced/signal/trap_with_on_signals.php" start="<?php\n\nnamespace signal;\n\n" %}
```

This example sends the signals to itself, but hitting `CTRL+C` once while the
process runs, then once after it, has the same effect and outputs:

```text
The process has been stopped with the exit code 130
SIGINT received by the task itself
And Castor is still running!
```

> [!WARNING]
> Trapping signals requires the `pcntl` extension. When it is not available -
> on Windows, for instance - the signals are not trapped, and Castor keeps its
> default behavior.
