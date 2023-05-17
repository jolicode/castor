# Castor

Castor is a PHP tool to create commands from a PHP function with helpers to
execute processes.

## Usage

As an example you could create a command that print "Hello castor" by creating a
file in `.castor/hello.php` with the following content:

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

Then you can run the command with `castor hello:castor`.

```bash
$ castor hello:castor
Hello castor
```

The name of the file without the extension is the namespace of the command, in
this case `hello`. The name of the function is the name of the command, in this
case `castor`.

## Installation

### Manually

For now, you'll need to clone the repository and run `composer install` to
install the project. Then create a symlink to the `castor` file in your `PATH`.

```bash
cd $HOME/somewhere
git clone git@github.com:jolicode/castor.git
cd castor
composer install
ln -s $PWD/bin/castor $HOME/.local/bin/castor
```

### As a PHAR

**Note:** This is not yet available.

You can download the latest version of Castor as a PHAR file from the [releases
page](https://github.com/jolicode/castor/releases)
