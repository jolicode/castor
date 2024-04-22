# Import remote functions

> [!WARNING]
> Remote imports is in a experimental state and may change in the future.

Castor can import functions from your filesystem but also from a remote resource.

## Installing remote packages

When importing functions from a remote resource, Castor will use Composer to
download the packages and store them in `.castor/vendor/`.

To import functions, you need to create a `castor.composer.json` file next to 
the `castor.php` file (either at the root of your project or in the `.castor/` 
directory).

This also can be done by running the `castor composer init` command.

See the [Composer documentation](https://getcomposer.org/doc/04-schema.md) for
more information about the `composer.json` file.

## Importing file from a remote package

Third party functions may not be autoloaded by Composer, as there may be 
optional. To import them, you can use the `import()` function.

```php
import('composer://vendor/package/', file: 'functions.php');
```

File is optional, if not provided, Castor will look for a `castor.php` file in 
the package.

## Manipulating castor composer file

Castor provide a `composer` command to manipulate the `castor.composer.json` 
file.

For example, you can use it to add a package to the file:

```bash
castor composer require 'vendor/package'
```

Or you can use it to update packages

```bash
castor composer update
```

## Preventing remote imports

In case you have trouble with the imported functions (or if you don't trust
them), you can prevent Castor from importing and running any of them. Add the
`--no-remote` option when calling any Castor tasks:

```bash
$ castor --no-remote my-task
```

This will trigger a warning to remind you that the remote imports are disabled.
Also, any task or configuration using an imported function will trigger an error
with Castor complaining about undefined functions.

If you want to disable remote imports every time, you can define the
`CASTOR_NO_REMOTE` environment variable to 1:

```bash
$ export CASTOR_NO_REMOTE=1
$ castor my-task # will not import any remote functions
```

## Lock file

Like every PHP projects using Composer, it will generate a 
`castor.composer.lock` file to lock the versions of the imported packages.

It is recommended to commit this file to your version control system to ensure
that everyone uses the same versions of the imported packages.
