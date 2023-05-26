# Castor

Castor is a DX oriented task runner and command launcher built with PHP.

It comes with many helpers to make your life easier:

* arguments and options parsing
* autocomplete
* process execution
* parallel processing
* file watching
* notification
* logging
* great DX

## Usage

As an example, you could create a command that print "Hello castor" by creating
a file `.castor.php` with the following content:

```php
<?php

namespace hello;

use Castor\Attribute\AsTask;
use function Castor\exec;

#[AsTask]
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

Then, you can go wild and create more complex commands:

```php
#[AsTask(description: 'Clean the infrastructure (remove container, volume, networks)')]
function destroy(
    SymfonyStyle $io,
    #[AsOption(
        description: 'Force the destruction without confirmation',
        shortcut: 'f',
        mode: InputOption::VALUE_NONE,
    )]
    bool $force,
) {
    if (!$force) {
        $io->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        $io->note('You can use the --force option to avoid this confirmation.');

        if (!$io->confirm('Are you sure?', false)) {
            $io->comment('Aborted.');

            return;
        }
    }

    log('Destroying the infrastructure...')

    exec('docker-compose down -v --remove-orphans --volumes --rmi=local');

    notify('The infrastructure has been destroyed.')
}
```

enhance

If you want to read more usage, you can read the [basic
usage](doc/01-basic-usage.md) documentation, or browse the [examples](examples)
directory.

## Autocomplete

If you use bash, you can enable autocomplete for castor by executing the
following command:

```
castor completion | sudo tee /etc/bash_completion.d/castor
```

Then reload your shell.

Others shells are also supported. To get the list of supported shells, run:

```
castor completion --help
```

## Installation

### As a PHAR

You can download the latest version of Castor as a PHAR file from the [releases
page](https://github.com/jolicode/castor/releases)

You can also download the latest version by browsing [the build
page](https://github.com/jolicode/castor/actions/workflows/build-phar.yml) and
selecting the last build.

### Manually

You'll need to clone the repository and run `composer install` to
install the project. Then create a symlink to the `castor` file in your `PATH`.

```bash
cd $HOME/somewhere
git clone git@github.com:jolicode/castor.git
cd castor
composer install
ln -s $PWD/bin/castor $HOME/.local/bin/castor
```

## Further documentation

Discover more by reading the docs

 * [Basic usage](doc/01-basic-usage.md)
 * [The exec function](doc/02-exec.md)
 * [Command arguments](doc/03-arguments.md)
 * [Using the context](doc/04-context.md)
 * [Asking something, progress bar and more](doc/05-helper.md)
 * [Watching files](doc/06-watch.md)
 * [Parallel processing](doc/07-parallel.md)
 * [Notification](doc/08-notify.md)
 * [Log](doc/09-log.md)
