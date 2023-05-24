## Watch

Castor provides a `watch` function that will watch a file or a directory and a callback function that will be called
when the file or directory changes.

```php
#[AsTask]
function watch(): void
{
    watch('src/', function ($file, $action) {
        echo "File {$file} has been {$action}\n";
    });
}
```

The action can be either `create`, `write`, `rename` or `remove` and the file will be the absolute path to the file.

### Recursive watch

By default the `watch` function will not watch subdirectories. You can change that by setting a specific path

```php
#[AsTask]
function watch(): void
{
    // watch recursively inside the src folder
    watch('src/...', function ($file, $action) {
        echo "File {$file} has been {$action}\n";
    });
}
```

### Stopping the watch

The `watch` function will look at the return value of the callback function. If the callback function returns `false`
the watch will stop.

```php
#[AsTask]
function watch(): void
{
    // watch recursively inside the src folder
    watch('src/...', function ($file, $action) {
        echo "File {$file} has been {$action}\n";
        return false;
    });
    echo 'stopped watching'; // will print stopped watching once a file has been modified in the src folder
}
```
