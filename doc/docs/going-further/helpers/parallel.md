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
{% include "/examples/advanced/parallel/jobs.php" start="<?php\n\nnamespace parallel;\n\n" %}
```

The values returned by the jobs are keyed by their name. The functions must be
either all named or all unnamed.

In an interactive terminal, each job is displayed as a block, with a spinner,
its duration and the last lines it printed. Once all the jobs are finished,
the full output of the failed ones is printed, with their error:

```text
✔ lint   0.9s
✘ test   failed after 1.3s
    Running the test suite
    Test 1: OK
    Test 2: OK
    Test 3: KO
⠹ build  1.5s
```

Otherwise, each line is printed as soon as it comes, prefixed with the name of
its job. What a job writes to the error output goes to the error output. When
a job fails, its error is printed with its output, and a summary of the jobs
comes at the end:

```text
[test]  Running the test suite
[lint]  Linting a.php
[test]  Test 1: OK
[lint]  Linting b.php
[lint]  ✔ Done in 0.9s
[test]  Test 2: OK
[test]  Test 3: KO
[test]  In jobs.php line 21:
[test]   [ERROR] The following process did not finish successfully (exit code 1):
[test]  echo "Test 3: KO" >&2; exit 1
[test]   // Re-run the command with the -v option to see more details.
[test]  ✘ Failed after 1.3s
[build] Assets compiled
[build] ✔ Done in 1.6s

[lint]  ✔ Done in 0.9s
[test]  ✘ Failed after 1.3s
[build] ✔ Done in 1.6s
```

The lines are prefixed:

* when the output is not an interactive terminal (CI, pipe, file…);
* in verbose and quiet modes;
* when there are more jobs than lines in the terminal;
* when `parallel()` is called from a function run by another `parallel()`
  (or by `watch()`): the jobs of a `parallel()` called inside a job are
  prefixed in the output of this job.

Inside a job, the output of `run()`, `io()`, `output()`, `echo` and the logs
is captured. Unless they have their own callback, the commands run without
TTY nor PTY, so they print plain logs instead of drawing for a terminal they
do not own.

The jobs run like any other function given to `parallel()`, so:

* a job only lets the others run, and the display refresh, while it waits for
  a command started with `run()`;
* a job cannot ask a question: its output is captured, so `io()->ask()` and
  the like get their default answer;
* `dump()` is not captured, it writes straight to the terminal.

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
