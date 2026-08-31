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

## Trapping signals sent to Castor

By default, a signal sent to Castor - like the `SIGINT` triggered by a `CTRL+C` -
interrupts Castor itself, and the task is not executed any further.

If you want to stop only the process currently running, and let the task
continue, you can ask the context to trap the signals with the
`withTrappedSignals()` method. When Castor receives one of these signals while
running a process, it forwards it to that process instead of being interrupted:

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
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\run;

#[AsTask(onSignals: [\SIGINT => 'onSigInt'])]
function foo(): void
{
    // While the process runs, the trap wins: the SIGINT is forwarded to the
    // process and the "onSignals" handler of the task is not called
    $process = run('sleep 10', context: context()->withTrappedSignals()->withAllowFailure());

    io()->writeln('The process has been stopped with the exit code ' . $process->getExitCode());

    // Once the process is finished, the trap is released and the "onSignals"
    // handler of the task gets the signals again
    sleep(10);

    io()->writeln('And Castor is still running!');
}

function onSigInt(int $signal): int|false
{
    io()->writeln('SIGINT received by the task itself');

    return false;
}
```

Hitting `CTRL+C` once during the first `run()`, then once during the `sleep()`,
outputs:

```text
The process has been stopped with the exit code 130
SIGINT received by the task itself
And Castor is still running!
```

> [!WARNING]
> Trapping signals requires the `pcntl` extension. When it is not available -
> on Windows, for instance - the signals are not trapped, and Castor keeps its
> default behavior.
