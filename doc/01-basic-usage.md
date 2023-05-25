## Basic usage

Castor use a convention to find commands. It will look for the
first `.castor.php` file in the current directory or in parents directory.

In this file, all functions with the `Castor\Attribute\AsTask` attribute will be
transformed as commands. The name of the function will be the name of the
command and the namespace will be the namespace of the command.

For example, if you have the following file:

```php
<?php

namespace hello;

use Castor\Attribute\AsTask;

#[AsTask]
function castor(): void
{
    echo 'Hello castor';
}

namespace foo;

use Castor\Attribute\AsTask;

#[AsTask]
function bar(): void
{
    echo 'Foo bar';
}
```

You will have two commands: `hello:castor` and `foo:bar`. If there is no
namespace then the command will have no namespace.

### Splitting commands in multiple files

#### Using a directory

Castor will also look for `.castor` directory in the directory of
the `.castor.php` file and load all the PHP files from it.

You could then have an empty `.castor.php` file and split your commands in
multiple files, like `.castor/hello.php` and
`.castor/foo.php`.

#### Using the `import` function

You can also use the `import()` function to import commands from another file.
This function takes a file path, or a directory as an argument.

When using a directory as an argument, Castor will load all the PHP files in it.

```php

use function Castor\import;

import(__DIR__ . '/custom-commands.php');
import(__DIR__ . '/my-app/.castor');

```

> :warning: You cannot dynamically import commands. The `import` function must
> be called at the top level of the file.

### Overriding command name, namespace or description

The `Castor\Attribute\AsTask` attribute takes three optional
arguments: `name`, `namespace` and `description` to override the default values.

```php
use Castor\Attribute\AsTask;

#[AsTask(name: 'bar', namespace: 'foo', description: 'Echo foo bar')]
function a_very_long_function_name_that_is_very_painful_to_write(): void
{
    echo 'Foo bar';
}
```
