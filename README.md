# Castor

Castor is a **<abbr title="Developer eXperience">DX</abbr> oriented task
runner** and **command launcher** built with PHP.

It comes with many helpers to make your life easier:

* Seamless parsing of **arguments and options**, simplifying input handling
* **Autocomplete** support for faster and error-free command entry
* Effortless **process execution**, enabling seamless integration with external
  tools
* **Parallel processing** capabilities to maximize resource utilization
* Intelligent **file watching** that automatically triggers actions on file
  modifications
* Customizable **notifications** to keep you informed and in control
* Robust **logging** for capturing and analyzing essential information
* A strong emphasis on exceptional **Developer Experience** (DX)

> **Note**
> Castor is still in early development, and the API is not stable yet. Even if
> it not likely to change, it is still possible that it will change in the
> future.

## Usage

As an example, you could create a command that prints "Hello from castor" by creating
a file `castor.php` with the following content:

```php
<?php

namespace greetings;

use Castor\Attribute\AsTask;
use function Castor\run;

#[AsTask]
function hello(): void
{
    run('echo "Hello from castor"');
}
```

Then you can run the command with `castor greetings:hello`:

```bash
$ castor greetings:hello
Hello from castor
```

Then, you can go wild and create more complex commands:

```php
#[AsTask(description: 'Clean the infrastructure (remove container, volume, networks)')]
function destroy(SymfonyStyle $io, bool $force = false)
{
    if (!$force) {
        $io->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        $io->comment('You can use the --force option to avoid this confirmation.');

        if (!$io->confirm('Are you sure?', false)) {
            $io->comment('Aborted.');

            return;
        }
    }

    run('docker-compose down -v --remove-orphans --volumes --rmi=local');

    notify('The infrastructure has been destroyed.')
}
```

If you want to read more about usage, you can read the [basic
usage](doc/01-basic-usage.md) documentation, or browse the [examples](examples)
directory.

## Installation

> **Note**
> Castor requires PHP >= 8.1 to run.

### As a phar - recommended way

You can download the latest release of Castor as a phar file from the [releases
page](https://github.com/jolicode/castor/releases).

You can also download the latest version by browsing [the build
page](https://github.com/jolicode/castor/actions/workflows/build-phar.yml) and
selecting the last build.

We provide different phar for Unix/Windows architectures to offer lighter phar
files. Download the correct one and make it available in your shell:

Example for Linux:
```bash
mv castor.linux-amd64.phar $HOME/.local/bin/castor
```

There are other ways to install Castor, please refer to the
[documentation](doc/01-installation.md).

## Further documentation

Discover more by reading the docs:

* [Installation and Autocomplete](doc/01-installation.md)
* [Basic Usage](doc/02-basic-usage.md)
* [The `run()` Function](doc/03-run.md)
* [Command Arguments](doc/04-arguments.md)
* [Using the Context](doc/05-context.md)
* [Asking Something, Progress Bar and more](doc/06-helper.md)
* [Watching Files](doc/07-watch.md)
* [Parallel Processing](doc/08-parallel.md)
* [Notification](doc/09-notify.md)
* [Log and Debug](doc/10-log.md)
