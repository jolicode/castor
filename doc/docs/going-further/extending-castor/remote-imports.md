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

When running Composer on `castor.composer.json`, Castor adds a repository of
[metapackages](https://getcomposer.org/doc/04-schema.md#type) standing for the
packages it ships, at their exact version, in front of the other repositories.
Their versions are the only ones Composer knows for these packages: your
packages are resolved against them, nothing is installed a second time in
`.castor/vendor/` (a metapackage has no files), and a package requiring a
version Castor does not ship is refused:

```console
$ castor my-task
...
  Problem 1
    - Root composer.json requires nikic/php-parser ^4.0, it is satisfiable by nikic/php-parser[v4.0.0, ..., v4.19.5] from composer repo (https://repo.packagist.org) but nikic/php-parser[v5.8.0] from the packages bundled with Castor v1.8.0 has higher repository priority. The packages from the higher priority repository do not match your constraint and are therefore not installable. ...

Set "extra.castor.bundled-packages" to false in castor.composer.json to ignore the packages bundled with Castor, at the risk of breaking it at runtime (see the documentation about remote imports).
```

The packages Castor ships appear in `castor.composer.lock` as metapackages,
marked with `extra.castor.bundled`, and `castor composer show` lists the ones
your packages use. When you update Castor, the next time it runs in the project
it checks that the versions it now ships still satisfy the constraints of your
packages, and only updates these entries of the lock when they do not, or when
it starts or stops shipping a package. The version recorded for a metapackage
is thus the one of the Castor that wrote it, and `castor composer update`
refreshes it.

Castor itself, `jolicode/castor`, is part of the list, so a remote package
requiring it is checked against the running version instead of installing a
second Castor in `.castor/vendor/`.

If you really need to install your own copy of a package Castor ships, because
a package you need has a constraint Castor does not satisfy for instance, opt
out in `castor.composer.json`:

```json
{
    "extra": {
        "castor": {
            "bundled-packages": false
        }
    }
}
```

Your packages are then resolved against Packagist only, and Castor may break at
runtime, when a class is loaded from the wrong copy.

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
