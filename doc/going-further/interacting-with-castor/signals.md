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

