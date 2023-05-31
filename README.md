# Castor

Castor is a DX oriented task runner and command launcher built with PHP.

It comes with many helpers to make your life easier:

* arguments and options parsing
* autocomplete
* process executing
* parallel processing
* file watching
* notification
* logging
* great DX

## Usage

As an example, you could create a command that prints "Hello castor" by creating
a file `castor.php` with the following content:

```php
<?php

namespace hello;

use Castor\Attribute\AsTask;
use function Castor\run;

#[AsTask]
function castor(): void
{
    run('echo "Hello castor"');
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

    run('docker-compose down -v --remove-orphans --volumes --rmi=local');

    notify('The infrastructure has been destroyed.')
}
```

If you want to read more about usage, you can read the [basic
usage](doc/01-basic-usage.md) documentation, or browse the [examples](examples)
directory.

## Autocomplete

If you use bash, you can enable autocomplete for castor by executing the
following command:

```
castor completion | sudo tee /etc/bash_completion.d/castor
```

Then reload your shell.

Others shells are also supported (zsh, fish, etc). To get the list of supported
shells and their dedicated instructions, run:

```
castor completion --help
```

## Installation

### As a PHAR

You can download the latest release of Castor as a PHAR file from the [releases
page](https://github.com/jolicode/castor/releases).

You can also download the latest version by browsing [the build
page](https://github.com/jolicode/castor/actions/workflows/build-phar.yml) and
selecting the last build.

We provide different PHAR for Unix/Windows architectures to offer lighter PHAR
files. Download the correct one and make it available in your shell:

Example for Linux:
```bash
mv castor.linux-amd64.phar $HOME/.local/bin/castor
```

### Globally with Composer

You can install Castor globally with Composer:

```bash
composer global require jolicode/castor
```

Then make sure that the Composer global bin directory is in your `PATH`:

```bash
export PATH="$HOME/.composer/vendor/bin:$PATH"
```

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

### With docker

If PHP is installed on your system, you can use the `castor` PHAR directly.
**This is the recommended way** because some features require to be executed on the host to work correctly,
like notification. However, if you don't have PHP installed, you can use docker.

We ship a `Dockerfile` that you can use to build a docker image with castor:

```
docker build -t castor .
```

Then you can run castor with:

```
docker run -it --rm -v `pwd`:/project castor
```

If you want to use docker commands in your tasks, you must enable docker
support when building the image:

```
docker build -t castor --build-arg WITH_DOCKER=1  .
```

Then you can run castor with:

```
docker run -it --rm -v `pwd`:/project -v "/var/run/docker.sock:/var/run/docker.sock:rw" castor
```

We suggest you to create an alias for it:

```
alias castor='docker run -it --rm -v `pwd`:/project -v "/var/run/docker.sock:/var/run/docker.sock:rw" castor'
```

## Further documentation

Discover more by reading the docs:

* [Basic usage](doc/01-basic-usage.md)
* [The `run()` function](doc/02-run.md)
* [Command arguments](doc/03-arguments.md)
* [Using the context](doc/04-context.md)
* [Asking something, progress bar and more](doc/05-helper.md)
* [Watching files](doc/06-watch.md)
* [Parallel processing](doc/07-parallel.md)
* [Notification](doc/08-notify.md)
* [Log](doc/09-log.md)
