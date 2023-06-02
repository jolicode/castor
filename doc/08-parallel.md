# Parallel execution

The `parallel()` function provides a way to run functions in parallel,
so you do not have to wait for a function to finish before starting another one:

```php
#[AsTask]
function foo(): void
{
    [$foo, $bar] = parallel(
        function () {
            return run('sleep 2 && echo foo', quiet: true);
        },
        function () {
            return run('sleep 2 && echo bar', quiet: true);
        }
    );

    echo $foo->getOutput(); // will print foo
    echo $bar->getOutput(); // will print bar
}
```

The `parallel()` function use the [`\Fiber`](https://www.php.net/Fiber) class to
run the functions in parallel.

> **Note**
> The code is not executed in parallel. Only functions using this concept
> will be executed in parallel, which is the case for
> the `run()` and `watch()` function.

## Watching in parallel

You can also watch in parallel multiple directories:

```php
#[AsTask]
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
