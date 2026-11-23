---
description: >
  Discover how to use Castor's `parallel()` function to run multiple functions
  concurrently, leveraging PHP Fibers for efficient, non-blocking execution.
---

# Parallel execution

This document explores how to execute multiple functions concurrently in Castor.

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

## Organizing the output with named jobs

When the functions print something, their output gets mixed. Name them, with
named arguments, and each one becomes a job whose output is grouped under its
name:

```php
{{ php("examples/advanced/parallel/jobs.php") }}
```

The values returned by the jobs are keyed by their name. The functions must be
either all named or all unnamed.

The jobs are displayed like [any other job](jobs.md#how-jobs-are-displayed):
in an interactive terminal, each one is a block with a spinner, its duration
and the last lines it printed, and the full output of the failed ones is
printed at the end:

```text
✔ lint   0.9s
✘ test   failed after 1.3s
    Running the test suite
    Test 1: OK
    Test 2: OK
    Test 3: KO
⠹ build  1.5s
```

When the output is not an interactive terminal (CI, pipe, file…), and in
verbose and quiet modes, each line is prefixed with the name of its job:

```text
[test]  Running the test suite
[lint]  Linting a.php
[test]  Test 1: OK
[lint]  Linting b.php
[lint]  ✔ Done in 0.9s
[test]  Test 2: OK
[test]  Test 3: KO
[test]  ✘ Failed after 1.3s
[test]  In jobs.php line 21:
[test]   [ERROR] The following process did not finish successfully (exit code 1):
[test]  echo "Test 3: KO" >&2; exit 1
[test]   // Re-run the command with the -v option to see more details.
[build] Assets compiled
[build] ✔ Done in 1.6s

[lint]  ✔ Done in 0.9s
[test]  ✘ Failed after 1.3s
[build] ✔ Done in 1.6s
```

A single display shows all the jobs: the jobs of a `parallel()` called inside
a job, or next to another one, are displayed with the others.

The jobs run like any other function given to `parallel()`, so a job only lets
the others run, and the display refresh, while it waits for a command started
with `run()`. Inside a job, the output is captured, see
[what happens inside a job](jobs.md#what-happens-inside-a-job).

> [!TIP]
> To build the jobs from a list, spread an array with string keys:
> `parallel(...$jobs)`.

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
