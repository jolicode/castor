# Import remote functions

Castor can import functions from your filesystem but also from a remote resource.

## Importing functions

When importing functions from a remote resource, Castor will use Composer to
download the packages and store them in `.castor/vendor/`.

To import functions, you need to use the same `import()` function used to import
your tasks, but this time with a different syntax for the `path` argument.

The import syntax depends on the source of the packages.

### From a Composer package (scheme `composer://`)

This is the most common use case when the functions to import are defined in a
Composer package. You can directly import them by using the package name
prefixed with the `composer://` scheme:

```php
use function Castor\import;

import('composer://vendor-name/project-name');
```

This will import all the tasks defined in the package.

#### Specify the version

You can define the version of the package to import by using the `version`
argument:

```php
use function Castor\import;

import('composer://vendor-name/project-name', version: '^1.0');
```

You can use any version constraint supported by Composer (like `*`, `dev-main`,
`^1.0`, etc.). See the [Composer documentation](https://getcomposer.org/doc/articles/versions.md#writing-version-constraints)
for more information.

> [!TIP]
> The `version` argument is optional and will default to `*`.

#### Import from a package not pushed to packagist.org

In some cases, you may have a Composer package that is not pushed to
packagist.org (like a private package hosted on packagist.com or another package
registry). In such cases, you can import it by using the `vcs` argument to
specify the repository URL where the package is hosted:

```php

use function Castor\import;

import('composer://vendor-name/project-name', vcs: 'https://github.com/organization/repository.git');
```

### From a repository (scheme `package://`)

If the functions you want to import are not available as a Composer package, you
can still import them by using a special configuration that Composer will
understand. This will now use the `package://` scheme.

```php
use function Castor\import;

import('composer://vendor-name/project-name', source: [
    'url' => 'https://github.com/organization/repository.git',
    'type' => 'git', // 'Any source type supported by Composer (git, svn, etc)'
    'reference' => 'main', //  A commit id, a branch or a tag name
]);
```

> [!NOTE]
> The "vendor-name/project-name" name can be whatever you want and we only be
> used internally by Castor and Composer to make the repository behave like a
> normal Composer package.

> [!TIP]
> Rather than using the `package://` scheme, it may be simpler to create a
> standard `composer.json` to your repository and import your newly created
> package by using the `composer://` scheme and the `vcs` argument.

## Import only a specific file

No matter where does the package come from (Composer package, git repository,
etc.), you can restrict the file (or directory) to be imported. This is
configured by using the `file` argument specifying the path inside the package
or repository.

```php
use function Castor\import;

import('composer://vendor-name/project-name', file: 'castor/my-tasks.php');
```

> [!NOTE]
> The `file` argument is optional and will empty by default, causing Castor to
> import and parse all the PHP files in the package. While handy, it's probably
> not what you want if your package contains PHP code that are not related to
> Castor.

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

## Update imported packages

When you import a package in a given version, Castor will not update
automatically update the packages once a new version of your dependency is
available.

To update your dependencies, you will either need to:
- change the required version yourself (thus every one using your Castor project
will profit of the update once they run your project);
- force the update on your side only by either using the `--update-remotes`
option or by removing the `.castor/vendor/` folder.

```bash
$ castor --update-remotes
```
