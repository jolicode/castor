
<h1 align="center">
    <img width="450" height="117" src="https://jolicode.com/media/original/castor-logo-line.svg?cool" alt="castor logo" />
</h1>

<p align="center">
    <i>Castor is a <strong><abbr title="Developer eXperience">DX</abbr> oriented task
    runner</strong> built in PHP featuring a range of functions for common task processing.</i>
</p>

It can be viewed as an alternative to Makefile, Fabric, Invoke, Shell scripts,
etc., but it leverages PHP's scripting capabilities and its extensive library ecosystem.

It comes with many features to make your life easier:

* Seamless parsing of **arguments and options**, simplifying input handling
* **[Autocomplete](https://castor.jolicode.com/going-further/interacting-with-castor/autocomplete)** support for faster and error-free typing
* A built-in list of useful functions:
    * [`run()`](https://castor.jolicode.com/getting-started/run/#the-run-function): Runs external processes, enabling seamless integration with external tools
    * [`parallel()`](https://castor.jolicode.com/going-further/helpers/parallel/#the-parallel-function): Parallelizes process execution to maximize resource utilization
    * [`watch()`](https://castor.jolicode.com/going-further/helpers/watch/): Watches files and automatically triggers actions on file modifications
    * [`log()`](https://castor.jolicode.com/going-further/interacting-with-castor/log/#the-log-function): Captures and analyzes essential information
    * [And even more advanced functions](https://castor.jolicode.com/reference/)

> [!NOTE]
> Castor is still in early development, and the API is not stable yet. Even if
> it is unlikely, it is still possible that it will change in the
> future.

## Usage

In Castor, tasks are set up as typical PHP functions marked with the `#[AsTask()]` attribute in a `castor.php` file.

These tasks can run any PHP code but also make use of various [functions for standard operations](https://castor.jolicode.com/reference/) that come pre-packaged with Castor.

For example:

```php
<?php

namespace greetings;

use Castor\Attribute\AsTask;
use function Castor\io;

#[AsTask()]
function hello(): void
{
    io()->writeln('Hello from castor');
}
```

Will expose a `greetings:hello` task that you can run with `castor greetings:hello`:

```bash
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

If you want to read more about usage, you can read the
[basic usage](https://castor.jolicode.com/getting-started/) documentation, or
watch [some examples](https://castor.jolicode.com/examples/).

## Installation

> [!NOTE]
> Castor requires PHP >= 8.1 to run.

### As a phar - recommended way

You can download the latest release of Castor as a phar file from the [releases
page](https://github.com/jolicode/castor/releases).

We provide different phar for Unix/Windows architectures to offer lighter phar
files. Download the correct one and make it available in your shell:

Example for Linux or macOS:
```bash
curl "https://raw.githubusercontent.com/jolicode/castor/main/installer/bash-installer" | bash
```

There are other ways to install Castor, please refer to the
[documentation](https://castor.jolicode.com/getting-started/installation/).

## Further documentation

Discover more by reading the docs:

* [Getting started with Castor](https://castor.jolicode.com/getting-started/)
  * [Installation and Autocomplete](https://castor.jolicode.com/getting-started/installation/)
  * [Basic Usage](https://castor.jolicode.com/getting-started/basic-usage/)
  * [Executing Processes with `run()`](https://castor.jolicode.com/getting-started/run/)
  * [Task Arguments](https://castor.jolicode.com/getting-started/arguments)
  * [Using the Context](https://castor.jolicode.com/getting-started/context)
* [Going further with Castor](https://castor.jolicode.com/going-further/)
* [Castor reference](https://castor.jolicode.com/reference/)
* [Examples](https://castor.jolicode.com/examples/)
* [Frequently asked questions](https://castor.jolicode.com/faq/)
