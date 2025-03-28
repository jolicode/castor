# Archive

## The `zip()` function

Castor provides a `zip()` function to compress files or directories into a password-protected ZIP archive:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Castor\Helper\CompressionMethod;

use function Castor\zip;
use function Castor\io;

#[AsTask(description: 'Compress a file into a zip archive')]
function compress_file(string $file, string $destination): void
{
    zip($file, $destination, 'my super secret password', CompressionMethod::DEFLATE, 6);
    io()->success('File compressed successfully!');
}
```

> [!NOTE]
> The `zip()` function automatically selects the best available method (binary or PHP) based on your system configuration.
> 
> The `source` parameter can be either a file path or a directory path. Both are fully supported.

> [!WARNING]
> If you're using the binary version of Castor, you must compile it with the ZIP extension enabled using the `--php-extensions=...,zip` option. Otherwise, the ZIP functionality will not be available.

## The `zip_binary()` function

Castor provides a `zip_binary()` function to compress files using the native zip binary:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Castor\Helper\ZipArchiver;

use function Castor\zip_binary;
use function Castor\io;

#[AsTask(description: 'Compress a directory into a zip archive using native binary')]
function compress_dir_binary(string $directory, string $destination): void
{
    zip_binary($directory, $destination, 'my super secret password', CompressionMethod::BZIP2, 9);
    io()->success('Directory compressed successfully!');
}
```

## The `zip_php()` function

Castor provides a `zip_php()` function to compress files using PHP's ZipArchive class:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;
use Castor\Helper\ZipArchiver;

use function Castor\zip_php;
use function Castor\io;

#[AsTask(description: 'Compress a directory into a zip archive using PHP')]
function compress_dir_php(string $directory, string $destination): void
{
    zip_php($directory, $destination, 'my super secret password', CompressionMethod::ZSTD, 9);
    io()->success('Directory compressed successfully!');
}
```

## Compression methods

Castor supports various compression methods:

- `CompressionMethod::STORE`: No compression, just storage (fastest)
- `CompressionMethod::DEFLATE`: Standard ZIP compression (good balance)
- `CompressionMethod::BZIP2`: Better compression ratio but slower
- `CompressionMethod::ZSTD`: Modern compression algorithm (best ratio, requires PHP ZipArchive with libzip ≥ 1.8.0)

## Overwriting existing archives

All zip functions accept an `overwrite` parameter to control whether existing archives should be replaced:

```php
// Will throw an exception if destination.zip already exists
zip($source, 'destination.zip', 'password');

// Will overwrite destination.zip if it already exists
zip($source, 'destination.zip', 'password', overwrite: true);
```
