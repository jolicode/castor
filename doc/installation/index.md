---
description: Installation instructions for Castor.
---

# Installing Castor and initial setup

## Installation methods

You can install Castor using different methods:

<div align="center" class="grid" style="margin: 3em 0">
<div>
<a href="./installer/" class="md-button md-button--primary" style="font-size: 2em; line-height: 2em; text-decoration: none">
    🚀 Official installer
</a>

<p style="font-style: italic;">
    The recommended way to install Castor.
</p>
</div>
<div>
<a href="./alternative-methods/" class="md-button" style="font-size: 2em; line-height: 2em; text-decoration: none">
    Alternative methods
</a>

<p style="font-style: italic;">
    Phar, static, Composer, git, etc.
</p>
</div>
</div>

<div align="center">
    You can also install Castor in a GitHub Action, see
    <a href="./github-action/">
        the dedicated documentation
    </a>
</div>

## Autocomplete

Castor provides a built-in autocomplete to ease its usage in shell.

See [the dedicated documentation](../going-further/interacting-with-castor/autocomplete.md)
to see how to install it, and also how to autocomplete your arguments.

## Stubs

The first time you run castor, it will create a `.castor.stub.php` at the root
directory of your project (where your `castor.php` is). This file contains some
definition of classes and methods from Castor and some of its dependencies.

This is useful when you install Castor from a phar, from a global composer
install, etc. Without it, your IDE would complain that it does not understand some
classes and would not provide any autocompletion in your castor files.

We suggest you to add this file to your `.gitignore` to not version it in git.
Castor will automatically update this file the first time you run Castor after
you install or update it.

> [!TIP]
> If you want to analyze your tasks with PHPStan, you will need to make PHPStan
> aware of some classes and functions definitions from Castor and its dependencies.
> To achieve this, add the stubs file in the `scanFiles` section of your `phpstan.neon`
> configuration file:

```neon
parameters:
    # ...
    scanFiles:
        - .castor.stub.php
```

If you don't want to generate stubs, you can use the `CASTOR_GENERATE_STUBS`
environment variables:

```bash
CASTOR_GENERATE_STUBS=0 castor
```

For convenience, you can export this variable in your shell configuration file:

```bash
echo 'export CASTOR_GENERATE_STUBS=0' >> ~/.bashrc
source ~/.bashrc
```

<!-- markdownlint-disable-file code-block-style -->
