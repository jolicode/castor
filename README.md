<div align="center">
    <img width="450" height="117" src="https://jolicode.com/media/original/castor-logo-line.svg?cool" alt="castor logo" />
</div>

<h1 align="center">
    A lightweight and modern task runner for Automation, CI/CD & DevOps.
</h1>

<div align="center">
    <strong>Automate everything. In PHP. Simply. Efficiently. Elegantly.</strong>
</div>

## 🚀 TL;DR

Write your automation scripts in PHP, run them from the CLI.<br/>
No need for Bash, Makefiles or YAML.<br/>

* ✅ 100% PHP — define tasks as simple PHP functions
* ⚡ Fast & native — no configuration, no boilerplate
* 🔧 Provided with a bunch of [useful built-in functions](reference.md)
* 🧠 [Autocompletion](going-further/interacting-with-castor/autocomplete.md) & descriptions for each task
* 🧰 Easy to integrate in your dev workflows

## 🤓 Presentation

<p align="center">
    <i>Castor is a <strong><abbr title="Developer eXperience">DX</abbr>-oriented task
    runner</strong> built in PHP featuring a range of functions for common task processing.</i>
</p>

It can be viewed as an alternative to Makefile, Fabric, Invoke, Shell scripts,
etc., but it leverages PHP's scripting capabilities and extensive library ecosystem.

It comes with many features to make your life easier:

* Seamless parsing of **arguments and options**, simplifying input handling
* **[Autocomplete](https://castor.jolicode.com/going-further/interacting-with-castor/autocomplete)** support for faster and error-free typing
* A built-in list of useful functions:
    * [`run()`](https://castor.jolicode.com/getting-started/run/#the-run-function): Run external processes, enabling seamless integration with external tools
    * [`io()`](https://castor.jolicode.com/going-further/helpers/console-and-io/#the-io-function): Display beautiful output and interacts with the terminal
    * [`watch()`](https://castor.jolicode.com/going-further/helpers/watch/): Watch files and automatically triggers actions on file modifications
    * [`fs()`](https://castor.jolicode.com/going-further/helpers/filesystem/#the-fs-function): Create, remove, and manipulate files and directories
    * [And even more advanced functions](https://castor.jolicode.com/reference/)

## 🧑‍🔬 Basic usage

In Castor, tasks are set up as typical PHP functions marked with the `#[AsTask()]` attribute in a `castor.php` file.

These tasks can run any PHP code but also make use of various [functions for standard operations](https://castor.jolicode.com/reference/) that come pre-packaged with Castor.

For example, the following castor.php file:

```php
use Castor\Attribute\AsTask;

#[AsTask()]
function hello(): void
{
    echo 'Hello from castor';
}
```

Will expose a `hello` task that you can run with `castor hello`:

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

→ Want to see basic usages and main features of Castor? Read the [Getting started documentation](https://castor.jolicode.com/getting-started/)

## 🤔 Why not Make / Robo / Phing / Deployer / Symfony Console?

Because:

* Make is not PHP, and is hard to maintain in large projects
* Others are either too verbose, OOP-heavy, requiring YML or XML configurations or are specialized in deployment only.
* Symfony Console is a great base — but Castor is built on top of it and gives you superpowers

→ See detailed comparisons in our [FAQ](https://castor.jolicode.com/faq/)

## 🧰 Get started in 10 seconds

```bash
curl "https://castor.jolicode.com/install" | bash

castor
```

→ Castor can also be installed in other ways (phar, static binaries, Composer), see [the installation documentation](https://castor.jolicode.com/getting-started/installation/).

## 📚 Want more?

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
