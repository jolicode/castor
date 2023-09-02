# Fingerprint

This package provides a `fingerprint` functionality to hash from string content, a file or a group of files (based on content or modification time) and execute tasks if the hash changes.

Take in consideration fingerprint SHOULD be of type string or callable that returns a string.

## Basic Usage

Consider the following example:

```php
<?php

use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;
use function Castor\finder;
use function Castor\hasher;

#[AsTask(description: 'Run a command only if the fingerprint has changed', fingerprint: 'fingerprint\fingerprintCheck')]
function simpleTask(): void
{
    echo "Hello Simple Task !\n";
}

function fingerprintCheck(): string
{
    return hasher()
        ->writeWithFinder(
            finder()
                ->in(__DIR__)
                ->name('*.fingerprint_single')
                ->files(),
            FileHashStrategy::Content
        )
        ->finish();
}
```

This `simpleTask()` function will execute only if the fingerprint (the hashed content of the file or files) changes. The file(s) taken into consideration for the hash are those with names matching the `'*.fingerprint_single'` pattern.

The package provides a fingerprint check function `fingerprintCheck()`, which returns the hash of the file's content.

If you want the task to execute if any file of a certain pattern changes, you can replace `'*.fingerprint_single'` with your desired pattern.

## Complex Tasks

We can also generate complex tasks which only execute if the hash changes.

```php
#[AsTask(description: 'Run a command only if the fingerprint has changed', fingerprint: 'fingerprint\fingerprintCheck2')]
function complexTask(): void
{
    simpleTask();
    echo "Hello Complex Task !\n";
}

function fingerprintCheck2(): string
{
    return hasher()
        ->writeWithFinder(
            finder()
                ->in(__DIR__)
                ->name('*.fingerprint_multiple')
                ->files(),
            FileHashStrategy::Content
        )
        ->finish();
}
```

In this snippet, `complexTask` will only run if the fingerprint of it changes. Note that sub-tasks do not count for the fingerprint and `simpleTask` will always until the fingerprint of `complexTask` changes.
Pay attention that he fingerprint of `simpleTask` is not taken into account, only the fingerprint of `complexTask` is. Check example below to see how to handle this.

## Task with Fingerprint Check Subroutines

Lastly, we can use `run()` function which only executes the tasked command if the fingerprint has changed.

```php
#[AsTask(description: 'Run a command every time, but just call some sub-task if fingerprint has changed')]
function inMethod(): void
{
    echo "Hello Fingerprint in Method !\n";
    run(
        'echo "Hey ! I\'m a sub-task ! Only executed if fingerprint has changed !"',
        fingerprint: hasher()
            ->writeWithFinder(
                finder()
                    ->in(__DIR__)
                    ->name('*.fingerprint_in_method')
                    ->files(),
                FileHashStrategy::Content
            )
            ->finish(),
    );
    echo "Is a thing was executed before me ?\n";
}
```

Here, the echo command is only executed if the fingerprint changes. Here the hash is always generated, but the command is only executed if the hash changes.
Is not dependent on task fingerprint, but on the fingerprint of the `run()` function.

## Hasher Helper Class

The `hasher()` function (under `Castor` namespace) provides various helper methods to generate hashes.

- `write()`: Writes a hash of a specific (string) value.
- `writeFile()`: Writes a hash of a file content or its modification time.
- `writeWithFinder()`: Writes a hash of a group of files obtained through Finder.
- `writeGlob()`: Writes a hash of a group of files obtained via a glob pattern.
- `writeTaskName()`: Writes the name of the current task.
- `writeTaskArgs()`: Writes arguments passed to the current task.
- `writeTask()`: Writes a combination of the current task name and arguments.
- `finish()`: Finalizes the hash operation, returning a string of the hash.

FileHashStrategy is an enum that contains two values: `Content` and `MTimes`. `Content` will make the hash dependent on the file's content, while `MTimes` will make it depend on the file's last modification time.

## Note

Always remember that the patterns used in Finder (`*.fingerprint_single`, `*.fingerprint_multiple` and `*.fingerprint_in_method`) are indicative. Replace them with the actual file patterns as per your requirements.
