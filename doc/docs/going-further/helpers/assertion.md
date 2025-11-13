# Assertion

## The `check()` function

Castor provides a `check()` function to ensure some requirements are met:

```php
use Castor\Attribute\AsTask;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\check;

#[AsTask()]
function git()
{
    check(
        'Check if Git is installed',
        'Git is not installed. Please install it before.',
        fn () => (new ExecutableFinder())->find('git'),
    );
}
```

## The `ProblemException` exception

If you must stop a task execution because of a problem, you can throw a
`ProblemException` exception:

```php
use Castor\Attribute\AsTask;
use Castor\Exception\ProblemException;

use function Castor\capture;

#[AsTask()]
function git()
{
    if (capture('git status --porcelain')) {
        throw new ProblemException('There are uncommitted changes.');
    }
}
```

It will stop the execution of the task and display the message in the console.
And it will also return a non-zero exit code (default to 1) to indicate that the
task failed.
