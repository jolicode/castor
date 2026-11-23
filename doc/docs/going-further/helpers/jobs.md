---
description: >
  Organize the output of the commands of a task in jobs: Castor shows each job
  with a spinner, its duration and its last lines, or prefixes its lines with
  its name.
---

# Organizing the output with jobs

A task that runs many commands prints a lot of output, which is hard to
follow. Group the commands in jobs, and Castor organizes their output under
the name of their job.

## Naming the job of a command

The job of a command is part of its context. Name it with the `withJob()`
method:

```php
{{ php("examples/advanced/job/sequential.php") }}
```

Several commands can share the same job: their output is shown together, and
the duration of the job is the total of the time its commands ran.

The `with()` function runs a whole function in a job, with its `job` argument:
the output of this function (`io()`, `output()`, `echo`, the logs) and the
one of the commands it runs are organized under the name of the job.

The functions given to [`parallel()`](parallel.md#organizing-the-output-with-named-jobs)
can be jobs too: name them, and each one becomes the job of the code it runs.

## How jobs are displayed

The jobs are displayed as blocks in an interactive terminal, and as prefixed
lines everywhere else.

### As blocks, in an interactive terminal

Each job is displayed as a block, with a spinner, its duration and the last
lines it printed. Once the command is finished, the full output of the failed
jobs is printed, with their error:

```text
✔ install  0.9s
✘ test     failed after 0.3s
    Running the test suite
    Test 1: OK
    Test 2: KO
```

The blocks are drawn at the bottom of the screen. A job that runs again
reuses its block, as long as it is still there. The blocks of the finished
jobs are left above, where they can not be redrawn anymore:

* when what does not run in a job writes to the console (`io()`, `run()`
  without a job…): it is printed below the blocks;
* when the blocks would not fit on the screen anymore.

A job that runs again after that gets a new block, at the bottom.

### As prefixed lines, everywhere else

When the output is not an interactive terminal (CI, pipe, file…), and in
verbose and quiet modes, each line is printed as soon as it comes, prefixed
with the name of its job. What a job writes to the error output goes to the
error output. Each time a job stops running, its status is printed, and a
summary of the jobs comes at the end when one of them failed:

```text
[install] Resolving dependencies
[install] Installed 42 packages
[install] ✔ Done in 0.6s
[test]    Running the test suite
[test]    Test 1: OK
[test]    Test 2: KO
[test]    ✘ Failed after 0.3s

[install] ✔ Done in 0.6s
[test]    ✘ Failed after 0.3s
```

## What happens inside a job

Inside a job, the output of `run()`, `io()`, `output()`, `echo` and the logs
is captured. Unless they have their own callback, the commands run without
TTY nor PTY, so they print plain logs instead of drawing for a terminal they
do not own.

As its output is captured:

* a job cannot ask a question: `io()->ask()` and the like get their default
  answer;
* `dump()` is not captured, it writes straight to the terminal.
