# Fingerprint

This package provides a `fingerprint` functionality to hash from string content, a file or a group of files (based on content or modification time) and execute tasks if the hash changes.

Take in consideration fingerprint SHOULD be of type string.

## Basic Usage

Consider the following example:

```php
<?php

use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;
use function Castor\finder;
use function Castor\fingerprint_exists;
use function Castor\fingerprint_save;
use function Castor\hasher;

#[AsTask(description: 'Run a command and run part of it only if the fingerprint has changed')]
function task_with_some_fingerprint(): void
{
    run('echo "Hello Task with Fingerprint !"');

    if (!fingerprint_exists(fingerprintCheck())) {
        run('echo "Cool, no fingerprint ! Executing..."');
        fingerprint_save(fingerprintCheck());
    }

    run('echo "Cool ! I finished !"');
}

function fingerprintCheck(): string
{
    return hasher()
        ->writeWithFinder(
            finder()
                ->in(__DIR__)
                ->name('*.json')
                ->files(),
            FileHashStrategy::Content
        )
        ->finish();
}
```

This `task_with_some_fingerprint()` function will execute only if the fingerprint (the hashed content of the file or files) changes. The file(s) taken into consideration for the hash are those with names matching the `'*.json'` pattern.

In example function `fingerprintCheck()`, which returns the hash of the file's content.

If you want the task to execute if any file of a certain pattern changes, you can replace `'*.json'` with your desired pattern.

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

Always remember that the patterns used in Finder (`*.json`) are indicative. Replace them with the actual file patterns as per your requirements.
