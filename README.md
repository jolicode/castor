<h1 align="center">
  <a href="https://github.com/jolicode/castor"><img src="https://jolicode.com/media/original/oss/headers/castor.png" alt="Castor"></a>
  <br />
  A lightweight and modern task runner for Automation, CI/CD & DevOps.<br>
  <sub><em><h6>Automate everything. In PHP. Simply. Efficiently. Elegantly.</h6></em></sub>
</h1>

<!-- start index -->

Write your automation scripts in PHP, run them from the CLI.<br/>
No need for Bash, Makefiles or YAML.<br/>

* ✅ 100% PHP — define tasks as simple PHP functions
* ⚡ Fast & native — no configuration, no boilerplate
* 🔧 Provided with a bunch of [useful built-in functions](https://castor.jolicode.com/reference/)
* 🧠 [Autocompletion](https://castor.jolicode.com/going-further/interacting-with-castor/autocomplete/) & descriptions for each task
* 🧰 Easy to integrate in your dev workflows

## Presentation

Castor is a <strong><abbr title="Developer eXperience">DX</abbr> oriented task
runner</strong>, that is designed to help you automate your development tasks
and workflows in a simple and efficient way.

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

## Get started in 10 seconds

```bash
curl "https://castor.jolicode.com/install" | bash

castor
```

→ Castor can also be installed in many other ways (phar, static binaries, Composer,
Github Action, etc), see [the installation documentation](https://castor.jolicode.com/getting-started/installation/).

## Basic usage

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

```console
$ castor hello
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

## Real-world use cases

* Run database migrations
* Deploy your app with one command
* Manage assets or translations
* Bootstrap environments
* Automate internal tools

→ See [more examples from the community](https://castor.jolicode.com/examples/#real-world-examples).

## What developers say

> "Finally a task runner that feels like PHP. No weird DSL, just functions."<br>
> — Every Castor user, probably
<!-- -->
> "I thought I needed Bash, Make, and half a DevOps degree. Turns out I just needed Castor."<br>
> — A surprisingly relieved developer
<!-- -->
> "We migrated from Make to Castor and nobody cried. That's a win."<br>
> — Senior Developer, now less grumpy

## Why not Make / Robo / Phing / Deployer / Symfony Console?

Because:

* Make is not PHP, and is hard to maintain in large projects
* Others are either too verbose, OOP-heavy, requiring YML or XML configurations or are specialized in deployment only.
* Symfony Console is a great base — but Castor is built on top of it and gives you superpowers

→ See detailed comparisons in our [FAQ](https://castor.jolicode.com/faq/)

<!-- end index -->

## Want more?

Discover more by reading the docs:

* [Installing Castor and initial setup](https://castor.jolicode.com/installation/)
* [Getting started with Castor](https://castor.jolicode.com/getting-started/)
    * [Basic Usage](https://castor.jolicode.com/getting-started/basic-usage/)
    * [Executing Processes with `run()`](https://castor.jolicode.com/getting-started/run/)
    * [Task Arguments](https://castor.jolicode.com/getting-started/arguments)
    * [Using the Context](https://castor.jolicode.com/getting-started/context)
    * [Remote execution](https://castor.jolicode.com/getting-started/remote)
* [Going further with Castor](https://castor.jolicode.com/going-further/)
* [Castor reference](https://castor.jolicode.com/reference/)
* [Examples](https://castor.jolicode.com/examples/)
* [Frequently asked questions](https://castor.jolicode.com/faq/)

<br><br>
<div align="center">
<a href="https://jolicode.com/"><img src="https://jolicode.com/media/original/oss/footer-github.png?v3" alt="JoliCode is sponsoring this project"></a>
</div>
