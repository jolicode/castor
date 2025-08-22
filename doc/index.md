---
title: Castor - A lightweight and modern task runner for PHP
description: Automate everything. In PHP. Simply. Efficiently. Elegantly.
---

<div align="center">
    <img width="450" height="117" src="https://jolicode.com/media/original/castor-logo-line.svg?cool" alt="castor logo" />
</div>

<h1 align="center" style="margin-top: 1em;">
    A lightweight and modern task runner for PHP.
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

Castor is a <strong><abbr title="Developer eXperience">DX</abbr> oriented task
runner</strong>, that is designed to help you automate your development tasks
and workflows in a simple and efficient way.

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
> While Castor hasn't reached v1.0 yet, any API changes are carefully managed
> with deprecation warnings and compatibility bridges.

## 🧑‍🔬 Basic usage

In Castor, tasks are set up as typical PHP functions marked with the `#[AsTask()]` attribute in a `castor.php` file.

These tasks can run any PHP code but also make use of various [functions for standard operations](reference.md) that come pre-packaged with Castor.

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

→ Want to see basic usages and main features of Castor? Read the [Getting started documentation](getting-started/index.md)

## 🧪 Real-world use cases

* Run database migrations
* Deploy your app with one command
* Manage assets or translations
* Bootstrap environments
* Automate internal tools

→ See [more examples from the community](examples.md#real-world-examples).

## 💬 What developers say

> "Finally a task runner that feels like PHP. No weird DSL, just functions."<br>
> — Every Castor user, probably
<!-- -->
> "I thought I needed Bash, Make, and half a DevOps degree. Turns out I just needed Castor."<br>
> — A surprisingly relieved developer
<!-- -->
> "We migrated from Make to Castor and nobody cried. That's a win."<br>
> — Senior Developer, now less grumpy

## 🤔 Why not Make / Robo / Phing / Deployer / Symfony Console?

Because:

* Make is not PHP, and is hard to maintain in large projects
* Others are either too verbose, OOP-heavy, requiring YML or XML configurations or are specialized in deployment only.
* Symfony Console is a great base — but Castor is built on top of it and gives you superpowers

→ See detailed comparisons in our [FAQ](faq.md)

## 🧰 Get started in 10 seconds

```bash
curl "https://castor.jolicode.com/install" | bash

castor
```

→ Castor can also be installed in other ways (phar, static binaries, Composer), see [the installation documentation](installation.md).

## 📚 Want more?

Discover more by reading all the docs:

* [Installation](installation.md)
* [Getting started with Castor](getting-started/index.md)
* [Going further with Castor](going-further/index.md)
* [Castor reference](reference.md)
* [Examples](examples.md)
* [Frequently asked questions](faq.md)
