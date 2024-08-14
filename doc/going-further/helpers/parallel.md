# Parallel execution

## The `parallel()` function

The `parallel()` function provides a way to run functions in parallel,
so you do not have to wait for a function to finish before starting another one:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\io;
use function Castor\parallel;

#[AsTask()]
function foo(): void
{
    [$foo, $bar] = parallel(
        function () {
            return run('sleep 2 && echo foo', context: context()->withQuiet());
        },
        function () {
            return run('sleep 2 && echo bar', context: context()->withQuiet());
        }
    );

    io()->writeln($foo->getOutput()); // will print foo
    io()->writeln($bar->getOutput()); // will print bar
}
```

The `parallel()` function use the [`\Fiber`](https://www.php.net/Fiber) class to
run the functions in parallel.

> [!NOTE]
> The code is not executed in parallel. Only functions using this concept
> will be executed in parallel, which is the case for
> the `run()` and `watch()` function.

## Watching in parallel

You can also watch in parallel multiple directories:

```php
use Castor\Attribute\AsTask;

use function Castor\parallel;

#[AsTask()]
function parallel_change()
{
    parallel(
        function () {
            watch('src/...', function (string $file, string $action) {
                // do something on src file change
            });
        },
        function () {
            watch('doc/...', function (string $file, string $action) {
                // do something on doc file change
            });
        },
    );
}
```
