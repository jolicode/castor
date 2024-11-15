# The DX oriented task runner in PHP

<div align="center">
    <img width="450" height="117" src="https://jolicode.com/media/original/castor-logo-line.svg?cool" alt="castor logo" />
</div>

## Presentation

Castor is a <strong><abbr title="Developer eXperience">DX</abbr> oriented task
runner</strong> built in PHP featuring a range of functions for common task
processing.

It can be viewed as an alternative to Makefile, Fabric, Invoke, Shell scripts,
etc., but it leverages PHP's scripting capabilities and its extensive library ecosystem.

It comes with many features to make your life easier:

* Seamless parsing of **arguments and options**, simplifying input handling
* **[Autocomplete](going-further/interacting-with-castor/autocomplete.md)** support for faster and error-free typing
* A built-in list of useful functions:
    * [`run()`](getting-started/run.md#the-run-function): Runs external processes, enabling seamless integration with external tools
    * [`io()`](going-further/helpers/console-and-io.md#the-io-function): Displays beautiful output and interacts with the terminal
    * [`watch()`](going-further/helpers/watch.md): Watches files and automatically triggers actions on file modifications
    * [`fs()`](going-further/helpers/filesystem.md/#the-fs-function): Creates, removes, and manipulates files and directories
    * [And even more advanced functions](reference.md)

> [!NOTE]
> Castor is still in early development, and the API is not stable yet. Even if
> it is unlikely to change, it is still possible that it will change in the
> future.

## Usage

In Castor, tasks are set up as typical PHP functions marked with the `#[AsTask()]` attribute in a `castor.php` file.

These tasks can run any PHP code but also make use of various [functions for standard operations](reference.md) that come pre-packaged with Castor.

For example:

```php
<?php

namespace greetings;

use Castor\Attribute\AsTask;
use function Castor\io;

#[AsTask()]
function hello(): void
{
    io()->write('Hello from castor');
}
```

Will expose a `greetings:hello` task that you can run with `castor greetings:hello`:

```shell
$ castor greetings:hello
Hello from castor
```

Then, you can go wild and create more complex tasks:

```php
#[AsTask(description: 'Clean the infrastructure (remove container, volume, networks)')]
function destroy(bool $force = false)
{
    if (!$force) {
        io()->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        io()->comment('You can use the --force option to avoid this confirmation.');

        if (!io()->confirm('Are you sure?', false)) {
            io()->comment('Aborted.');

            return;
        }
    }

    run('docker-compose down -v --remove-orphans --volumes --rmi=local');

    notify('The infrastructure has been destroyed.')
}
```

## Further documentation

Discover more by reading the docs:

* [Installation](installation.md)
* [Getting started with Castor](getting-started/index.md)
* [Going further with Castor](going-further/index.md)
* [Castor reference](reference.md)
* [Examples](examples.md)
* [Frequently asked questions](faq.md)
