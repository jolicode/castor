# Watching file changes

Castor provides a `watch()` function that will watch a file or a directory and
call a callback function when the file or directory changes:

```php
use Castor\Attribute\AsTask;

use function Castor\watch;

#[AsTask]
function watch(): void
{
    watch('src/', function (string $file, string $action) {
        echo "File {$file} has been {$action}\n";
    });
}
```

`$action` can be either `create`, `write`, `rename` or `remove` and the file
will be the absolute path to the file.

## Recursive watch

By default the `watch()` function will not watch subdirectories. You can change
that by passing a path suffixed by `/...`:

```php
use Castor\Attribute\AsTask;

use function Castor\watch;

#[AsTask]
function watch(): void
{
    // watch recursively inside the src folder
    watch('src/...', function (string $file, string $action) {
        echo "File {$file} has been {$action}\n";
    });
}
```

## Stopping the watch

The `watch()` function will look at the return value of the callback function. If
the callback function returns `false` the watch will stop:

```php
use Castor\Attribute\AsTask;

use function Castor\watch;

#[AsTask]
function watch(): void
{
    // watch recursively inside the src folder
    watch('src/...', function (string $file, string $action) {
        echo "File {$file} has been {$action}\n";
        return false;
    });
    echo 'stopped watching'; // will print "stopped watching" once a file has been modified in the src folder
}
```

## Watching multiple paths

The `watch()` function can watch multiple paths at the same time:

```php
use Castor\Attribute\AsTask;

use function Castor\watch;

#[AsTask]
function watch(): void
{
    // watch recursively inside the src and tests folders
    watch(['src/...', 'tests/...'], function (string $file, string $action) {
        echo "File {$file} has been {$action}\n";
    });
}
```
