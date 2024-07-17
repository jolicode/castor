# Fingerprinting and code execution when something changes

Castor provides a `fingerprint` functionality to execute tasks only if something
changed. To detect whether something has changed, Castor uses a fingerprint that
can be anything like a string content, the hash of a file or a group of files
(based on content or modification time), etc.

## The `fingerprint()` function

You can use the `fingerprint()` function to conditionally execute a callback if
the given fingerprint has changed.

```php
use Castor\Attribute\AsTask;
use function Castor\fingerprint;
use function Castor\io;

#[AsTask(description: 'Execute a callback only if the fingerprint has changed')]
function task_with_a_fingerprint(): void
{
    fingerprint(
        callback: function () {
            io()->writeln('Cool, no fingerprint! Executing...');
        },
        id: 'my-fingerprint',
        fingerprint: "my fingerprint",
    );
}
```

> [!NOTE]
> You can use the `$force` parameter of the `fingerprint()` function to force
> the execution of the callback even if the fingerprint has not changed.

## The `hasher()` function

Most of the time, you will want your fingerprint hash to be based on the content
of a file, to scope it to a specific task or something else. To help you compute
this hash, Castor provides a `hasher()` function. It returns an instance of
`Castor\Helper\HasherHelper` with various helper methods:

- `write()`: Writes a hash of a specific (string) value.
- `writeFile()`: Writes a hash of a file content or its modification time.
- `writeWithFinder()`: Writes a hash of a group of files obtained through
[Finder](filesystem.md#the-finder-function).
- `writeGlob()`: Writes a hash of a group of files obtained via a glob pattern.
- `writeTaskName()`: Writes the name of the current task.
- `writeTaskArgs()`: Writes arguments passed to the current task.
- `writeTask()`: Writes a combination of the current task name and arguments.
- `finish()`: Finalizes the hash operation, returning a string of the hash.

The methods `writeFile()`, `writeWithFinder()` and `writeGlob()` accept a second
parameter `$strategy` to specify on with criteria the hash should be based on.
This parameter is a `Castor\Fingerprint\FileHashStrategy` enum that contains two
values:

- `Content` will make the hash dependent on the file's content
- `MTimes` will make the hash depend on the file's last modification time. This
is the default strategy.

Example usage:

```php
use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;
use function Castor\fingerprint;
use function Castor\hasher;

#[AsTask(description: 'Execute a callback only if the fingerprint has changed')]
function task_with_a_fingerprint(): void
{
    fingerprint(
        callback: function () {
            io()->writeln('Executing the callback because my-file.json has changed.');
        },
        id: 'task-id',
        fingerprint: hasher()->writeFile('my-file.json', FileHashStrategy::Content)->finish(),
    );
}
```

## The `fingerprint_exists()` and `fingerprint_save()` functions

If you want more control over the fingerprint behaviour, you can use the
`fingerprint_exists()` and `fingerprint_save()` functions to conditionally
execute your code:

```php
use Castor\Attribute\AsTask;
use Castor\Fingerprint\FileHashStrategy;
use function Castor\finder;
use function Castor\fingerprint_exists;
use function Castor\fingerprint_save;
use function Castor\hasher;

#[AsTask(description: 'Check if the fingerprint has changed before executing some code')]
function task_with_some_fingerprint(): void
{
    if (!fingerprint_exists('task-id', my_fingerprint_check())) {
        io()->writeln('Executing some code because fingerprint has changed.');
        fingerprint_save('task-id', my_fingerprint_check());
    }
}

function my_fingerprint_check(): string
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
