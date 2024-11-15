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
> Related example: [configuration.php](https://github.com/jolicode/castor/blob/main/examples/configuration.php)

Castor will also look for a `.castor/castor.php` file in the current directory,
or in its parents.

The `castor.php` file has an higher priority than the `.castor/castor.php` file.

## Splitting tasks in multiple files

## The `import()` function

You can use the `import()` function to import tasks from another file or
directory. This function takes a file path, or a directory as an argument.

When using a directory as an argument, Castor will load all the PHP files in it:

```php
use function Castor\import;

import(__DIR__ . '/custom-commands.php');
import(__DIR__ . '/my-app/castor');
```

> [!WARNING]
> You cannot dynamically import tasks. The `import()` function must be called
> at the top level of the file.

If you use the `.castor/castor.php` layout, you can use the following code to
load all files in the `.castor/` directory:

```php
// .castor/castor.php
use function Castor\import;

import(__DIR__);
```

> [!NOTE]
> You can also import functions from a remote resource. See the
> [related documentation](../going-further/extending-castor/remote-imports.md).

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
> Related example: [configuration.php](https://github.com/jolicode/castor/blob/main/examples/configuration.php)

## Setting a default task

The `Castor\Attribute\AsTask` attribute allows you to set a default task when
calling `castor` without any arguments:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask(description: 'Displays some help and available urls for the current project', default: true)]
function about(): void
{
    io()->title('About this project');

    io()->comment('Run <comment>castor</comment> to display all available commands.');
    io()->comment('Run <comment>castor about</comment> to display this project help.');
    io()->comment('Run <comment>castor help [command]</comment> to display Castor help.');
}
```
