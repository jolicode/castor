# Basic usage

Castor use a convention to find tasks. It will look for the
first `castor.php` file in the current directory or in parents directory.

In this file, all functions with the `Castor\Attribute\AsTask` attribute will be
transformed as tasks. The name of the function will be the task's name
and the namespace will be the task's namespace.

For example, if you have the following file:

```php
<?php

namespace hello;

use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function castor(): void
{
    io()->writeln('Hello castor');
}

namespace foo;

use function Castor\io;

use Castor\Attribute\AsTask;

#[AsTask()]
function bar(): void
{
    io()->writeln('Foo bar');
}
```

You will have two tasks: `hello:castor` and `foo:bar`. If there is no
namespace then the task will have no namespace.

From now on, we will omit the leading `<?php` in all doc examples.

> [!TIP]
> Related example: [foo.php](https://github.com/jolicode/castor/blob/main/examples/foo.php)

## Splitting tasks in multiple files

### Using a directory

Castor will also look for `castor` directory in the same directory of
the `castor.php` file and load all the PHP files from it.

You could then have an empty `castor.php` file and split your tasks in
multiple files, like `castor/hello.php` and `castor/foo.php`.

### The `import()` function

You can also use the `import()` function to import tasks from another file.
This function takes a file path, or a directory as an argument.

When using a directory as an argument, Castor will load all the PHP files in it:

```php
use function Castor\import;

import(__DIR__ . '/custom-commands.php');
import(__DIR__ . '/my-app/castor');
```

> [!WARNING]
> You cannot dynamically import tasks. The `import()` function must be called
> at the top level of the file.

## Overriding task name, namespace or description

The `Castor\Attribute\AsTask` attribute takes three optional
arguments: `name`, `namespace` and `description` to override the default values:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(name: 'bar', namespace: 'foo', description: 'Output foo bar')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    io()->writeln('Foo bar');
}
```

> [!TIP]
> Related example: [foo.php](https://github.com/jolicode/castor/blob/main/examples/foo.php)
