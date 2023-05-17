# Castor

Castor is a PHP tool to create commands from a PHP function with helpers to execute processes.

## Usage

As an example you could create a command that print 'Hello castor' by creating a file in `.castor/hello.php` with the following content:

```php
<?php

use Castor\Attribute\Task;
use function Castor\exec;

#[Task]
function castor(): void
{
    exec('echo "Hello castor"');
}
```

Then you can run the command with `php castor.phar hello:castor`.

```bash
$ php castor.phar hello:castor
Hello castor
```

The name of the file without the extension is the namespace of the command, in this case `hello`. 
The name of the function is the name of the command, in this case `castor`.

## Installation

You can download the latest version of Castor as a phar file from the [releases page](https://github.com/jolicode/castor/releases)