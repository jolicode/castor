---
description: >
  Learn how to import remote functions in Castor using Composer, manage
  dependencies with the `castor composer` command, and control remote imports
  for security.
---

# Import remote functions

In addition to loading functions locally from your filesystem, Castor can also
import external functions remote resources. This document explains how to
achieve this.

> [!WARNING]
> Remote imports is in an experimental state and may change in the future.

## Installing remote packages

When importing functions from a remote resource, Castor will use Composer to
download the packages and store them in `.castor/vendor/`.

To import functions, you need to create a `castor.composer.json` file next to
the `castor.php` file (either at the root of your project or in the `.castor/`
directory).

This also can be done by running the `castor composer init` command.

See the [Composer documentation](https://getcomposer.org/doc/04-schema.md) for
more information about the `composer.json` file.

## Packages bundled with Castor

Castor ships its own dependencies (Symfony components, `nikic/php-parser`,
`composer/composer`, ...) in the phar and in the static binary, and loads them
in the same PHP process as the remote packages. A PHP class is loaded once, so a
remote package cannot bring its own copy of one of these packages: the process
would run a mix of the two versions, and break in unexpected ways.

When installing the remote packages, Castor declares the packages it ships as
replaced by your `castor.composer.json`, like the
[`replace` key](https://getcomposer.org/doc/04-schema.md#replace) of a
`composer.json` does. Composer then resolves your packages against the versions
Castor ships, does not install them a second time in `.castor/vendor/`, and
refuses a package requiring a version Castor does not ship:

```console
$ castor my-task
...
  Problem 1
    - Root composer.json requires nikic/php-parser ^4.0 -> found nikic/php-parser[v4.0.0, ..., v4.19.4] but these were not loaded, likely because it conflicts with another require.

The remote packages are loaded in the same PHP process as Castor v1.8.0, which already provides nikic/php-parser (v5.8.0): the packages required in castor.composer.json must be compatible with these versions. ...
```

Run `castor composer show --self` to list the packages Castor ships, in the
`replaces` section. The list includes the virtual `castor/castor` package,
standing for the running Castor version: the author of a remote package can
require it to declare the Castor versions the package supports, like
`"castor/castor": "^1.8"`.

When you update Castor, the packages it now ships are removed from
`castor.composer.lock` automatically the next time Castor runs in the project,
and the other packages keep their locked version.

If you really need to install your own copy of a package Castor ships, because
a package you need has a constraint Castor does not satisfy for instance, opt
out in `castor.composer.json`:

```json
{
    "extra": {
        "castor": {
            "replace-bundled-packages": false
        }
    }
}
```

Castor may then break at runtime, when a class is loaded from the wrong copy.

## Importing file from a remote package

Third party functions may not be autoloaded by Composer, as they may be
optional. To import them, you can use the `import()` function.

```php
import('composer://vendor/package/', file: 'functions.php');
```

The `file` argument is optional. When not provided, Castor will look for a
`castor.php` file in the package.

## Manipulating castor composer file

Castor provides a `composer` command to manipulate the `castor.composer.json`
file.

For example, you can use it to add a package to the file:

```bash
castor composer require 'vendor/package'
```

Or you can use it to update packages:

```bash
castor composer update
```

## Preventing remote imports

Remote packages are code downloaded from Packagist (or the repositories
declared in `castor.composer.json`) and executed on your machine, like the
`castor.php` file itself. They are installed the first time Castor runs in the
project, whatever the command (`castor list` included), except during a shell
completion, which never downloads anything.

In case you have trouble with the imported functions (or if you don't trust
them), you can prevent Castor from importing and running any of them. Add the
`--no-remote` option when calling any Castor tasks:

```bash
castor --no-remote my-task
```

This will trigger a warning to remind you that the remote imports are disabled.
Also, any task or configuration using an imported function will trigger an error
with Castor complaining about undefined functions.

If you want to disable remote imports every time, you can define the
`CASTOR_NO_REMOTE` environment variable to 1:

```bash
export CASTOR_NO_REMOTE=1
castor my-task # will not import any remote functions
```

## Lock file

Like every PHP projects using Composer, it will generate a
`castor.composer.lock` file to lock the versions of the imported packages.

It is recommended to commit this file to your version control system to ensure
that everyone uses the same versions of the imported packages.
