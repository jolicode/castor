---
description: Most frequently asked questions about Castor.
---

# Frequently Asked Questions

## Differences with **Make**?

See below how **Castor** is different from **Make**:

| Criterion                 | **Castor**                                | **Make**                                                    |
|---------------------------|-------------------------------------------|-------------------------------------------------------------|
| **Language**              | **PHP** — your own language               | **Makefile syntax** (custom DSL)                            |
| **Learning curve**        | Easy for any PHP dev                      | Steep if you're not familiar with Makefile syntax nor Shell |
| **Task definition**       | PHP functions with attributes             | Rule-based, using targets and dependencies                  |
| **Dynamic logic**         | ✅ Native in PHP (conditions, loops, etc.) | ⚠️ Harder — requires shell scripting or complex rules       |
| **Error handling**        | Try/catch, logging, etc. in PHP           | Shell-based error codes and operators (like `&&`)           |
| **Dependencies**          | Composer-managed, remote imports          | External shell commands                                     |
| **Cross-platform**        | ✅ Fully portable (runs with PHP)          | ⚠️ Depends on shell tools — may vary on Windows/Linux       |
| **Designed for PHP devs** | ✅ Yes                                     | ❌ Not really                                                |

TL;DR:

* **Make** is great for compiling C projects in 1995.
* **Castor** is great for automating (PHP or not) projects in 2025.

> Make is powerful, but its syntax is obscure and hard to debug.<br>
> Castor lets you write tasks in PHP — the language you already know.

## Differences with **Robo**?

See below how **Castor** is different from **Robo**:

| Criterion                 | **Castor**                                   | **Robo**                                                 |
|---------------------------|----------------------------------------------|----------------------------------------------------------|
| **Philosophy**            | Simple PHP **functions with attributes**     | **OOP-based** task classes                               |
| **Task definition**       | Annotated PHP functions (`#[AsTask]`)        | Methods inside a class extending `Tasks`                 |
| **Installation**          | Phar, static binary, Composer, GitHub Action | Phar, Composer                                           |
| **CLI autocompletion**    | ✅ Built-in                                   | ❌ Not by default                                         |
| **Learning curve**        | ✅ Very low – just write a function           | ⚠️ Requires boilerplate and understanding of inheritance |
| **Modern PHP**            | ✅ Uses modern features (attributes, PHP 8+)  | ⚠️ More traditional OOP, less "modern PHP"-oriented      |
| **Symfony Console-based** | ✅ Yes (under the hood)                       | ✅ Yes (used directly)                                    |
| **Community**             | Small but active                             | Larger but not very active                               |

TL;DR:

* Castor is minimal, expressive, and easy to use. You define tasks as plain PHP functions with attributes — that’s it.
* Robo is powerful but more verbose and class-oriented. It might feel too heavy for small or script-like automation needs.

> If you prefer "just PHP" over complex CLI frameworks, you'll love Castor.<br>
> If you're building a full-featured CLI app, Robo might fit — but Castor often gets you there faster.

## Differences with **Phing**?

See below how **Castor** is different from **Phing**:

| Criterion           | **Castor**                           | **Phing**                               |
|---------------------|--------------------------------------|-----------------------------------------|
| **Main focus**      | General-purpose task runner          | Build system (tests, packaging)         |
| **Config language** | Native PHP (functions + attributes)  | ⚠️ XML (`build.xml`)                    |
| **Ease of use**     | ✅ High (readable, IDE friendly)      | ❌ Low (verbose XML, harder to maintain) |
| **Modernity**       | ✅ Very modern (PHP 8+, DX-focused)   | ⚠️ Old (Ant-inspired, legacy)           |
| **Built-in tasks**  | ✅ Lightweight, extensible in PHP     | Large catalog of built-in tasks         |
| **Learning curve**  | Gentle (PHP developers feel at home) | Steep (XML + conventions)               |
| **Community**       | Young, growing                       | Historical, smaller today               |
| **Best suited for** | Local automation, CI/CD, DevOps      | Legacy projects, complex build scripts  |

TL;DR:

* Castor is modern, minimal, and uses plain PHP functions with attributes.
* Phing is XML-heavy, verbose, and feels like it belongs in a museum next to Ant and SOAP.

> Phing shines if you love writing <target> blocks and closing tags.<br>
> Castor shines if you prefer actual PHP code in 2025 instead of XML from 2005.

## Differences with **Deployer**?

See below how **Castor** is different from **Deployer**:

| Criterion           | **Castor**                          | **Deployer**                                              |
|---------------------|-------------------------------------|-----------------------------------------------------------|
| **Main focus**      | General-purpose task runner         | Deployment automation (SSH orchestration)                 |
| **Config language** | Native PHP (functions + attributes) | Native PHP (`deploy.php`)                                 |
| **Ease of use**     | High (simple, IDE autocomplete)     | Medium (requires deployment knowledge)                    |
| **Modernity**       | Very modern (PHP 8+, DX-focused)    | Mature, widely adopted                                    |
| **Built-in tasks**  | Lightweight, generic (extensible)   | Rich deployment-specific plugins (Symfony, Laravel, etc.) |
| **Learning curve**  | Gentle (PHP-centric)                | Medium (deployment flow concepts)                         |
| **Community**       | Young, growing                      | Large, strong adoption in production                      |
| **Best suited for** | Automation, CI/CD, local tooling    | Deployments and server orchestration                      |

TL;DR:

* Castor is a general-purpose task runner: tests, CI/CD, local automation, Docker, Ansible… all in plain PHP.
* Deployer is laser-focused on one thing: pushing your code to servers.

> If you need a Swiss Army knife for automation, Castor has your back.<br>
> If your only goal is “ship this to production”, Deployer is the specialist.

## How is **Castor** different from raw **Symfony Console** usage?

Castor is a task runner, so it's primary goal is to run simple tasks to simplify
the project development. Usually, it is used to run Docker commands, database
migrations, cache clearing, etc.

Usually, tasks are very small, like 1 or 2 lines of code. So you probably don't
want to waste your project with ops command that are not strictly related to the
business.

## Is it safe to run Castor in a project I do not trust?

No more than running `make`, `composer install` or `npm install` there: the
`castor.php` file is PHP code, and Castor runs it to discover the tasks. So
**every** Castor command runs it, including `castor list`, `castor --help` and
the shell completion, before any task is executed. The same goes for the
[remote packages](../docs/going-further/extending-castor/remote-imports.md)
declared in `castor.composer.json`, which are downloaded and loaded the first
time Castor runs in the project (never during a shell completion).

So, before running Castor in a project you just cloned, read its `castor.php`
file, and its `castor.composer.json` and `castor.composer.lock` files, like you
would read a `Makefile` or a `composer.json`. The `--no-remote` option, or the
`CASTOR_NO_REMOTE` environment variable, keeps Castor from installing and
loading the remote packages.

## Why "Castor"?

Castor means "beaver" in french. It's an animal building stuff. And this is what
this tool does: it helps you build stuff 😁
