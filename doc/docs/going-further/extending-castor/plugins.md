---
description: >
  Learn how to use the tasks, contexts and listeners of a Castor plugin with
  `register_plugin()`, pick its features, prefix its tasks, and how to write a
  plugin.
---

# Plugins

A plugin is a Composer package giving tasks, contexts or listeners to the
projects using it. Requiring a package in `castor.composer.json` (see
[remote packages](remote-imports.md)) only gives its functions and classes:
its tasks, contexts and listeners are exposed once you register it.

## Using a plugin

### Requiring and registering a plugin

Require the package, then register it in your `castor.php` file with the
`register_plugin()` function:

```bash
castor composer require vendor/package
```

```php
use function Castor\register_plugin;

register_plugin('vendor/package');
```

When the package is a plugin, `castor composer require` offers to add this call
to your `castor.php` file for you. When it cannot (no `castor.php` file, no
interaction, or code it cannot change safely), it prints the lines to add.
`castor composer remove` does not remove the call: remove it yourself.

> [!NOTE]
> `register_plugin()` takes the name of the package, without `composer://`. The
> package must be required, in the `castor.composer.json` file of the project or
> of a [mounted directory](mount.md): Castor stops with an error otherwise.

### The features of a plugin

By default, `register_plugin()` exposes the tasks, contexts and listeners of the
files the plugin autoloads: its `default` feature. A plugin can also declare
other features, sets of files that are only loaded when you ask for them:

```php
use function Castor\register_plugin;

// Only the "qa" feature, without the default one
register_plugin('vendor/package', 'qa');

// The default feature and the "qa" and "docker" ones
register_plugin('vendor/package', ['default', 'qa', 'docker']);
```

Asking for a feature replaces the default one: add `default` to the list to
keep it. Calling `register_plugin()` again for the same plugin adds the new
features, and asking again for a feature already loaded does nothing. Asking for
a feature the plugin does not declare stops Castor with an error listing the
features it has.

### Prefixing the tasks of a plugin

The `prefix` argument is prepended to the namespace of the tasks of the plugin,
to avoid conflicts with your own tasks, or the ones of another plugin:

```php
use function Castor\register_plugin;

register_plugin('vendor/package', prefix: 'app');
```

A task `qa:lint` of the plugin is then available as `app:qa:lint`. Like with
[`mount()`](mount.md), only the namespace of the tasks changes: the names of
the contexts, the listeners and the aliases of the tasks are left as they are.

The tasks of a plugin are loaded once, so a plugin has a single prefix: calling
`register_plugin()` again for the same plugin with another prefix stops Castor
with an error.

### Working directory

The tasks of a plugin run in the working directory of the context, like your
own tasks. Unlike with [`mount()`](mount.md), they do not run in the directory
of the package, which lives in `.castor/vendor/`: a plugin knows that it is a
package, and uses `__DIR__` to reach its own files.

### Disabling plugins

With the `--no-remote` option, or the `CASTOR_NO_REMOTE` environment variable,
the remote packages are neither installed nor loaded, and `register_plugin()`
does nothing (see [preventing remote imports](remote-imports.md#preventing-remote-imports)).

## Writing a plugin

A plugin is a Composer package like any other: declare its functions, its
classes, and the files of its tasks, contexts and listeners in the `autoload`
section of its `composer.json` file. It does not need a `castor.php` file.

```json
{
    "name": "vendor/package",
    "type": "castor-plugin",
    "autoload": {
        "psr-4": {
            "Vendor\\Package\\": "src/"
        },
        "files": [
            "src/functions.php"
        ]
    }
}
```

The tasks, contexts and listeners of the files the package autoloads are its
`default` feature: a project gets them with `register_plugin('vendor/package')`.

> [!NOTE]
> The functions and the classes of the package are available as soon as a
> project requires it, even without registering it: a function with the
> `AsTask` attribute is just a function until then.

### Declaring features

Declare the features of the plugin in the `extra.castor.features` section of
its `composer.json` file: each feature is a list of files or directories,
relative to the package. The PHP files of a directory are all loaded.

```json
{
    "extra": {
        "castor": {
            "features": {
                "qa": ["src/qa.php"],
                "docker": ["src/docker/"]
            }
        }
    }
}
```

The files of a feature must not be autoloaded: Composer would load them with the
package, so the feature could not expose their tasks, contexts and listeners.
Castor stops with an error when it finds one in the `autoload` section of the
package. A path outside of the package is refused too.

A feature named `default` replaces what the package autoloads as the feature
loaded by default. A plugin keeping its own `castor.php` file as the entry point
for the projects using it declares it there:

```json
{
    "extra": {
        "castor": {
            "features": {
                "default": ["castor.php"]
            }
        }
    }
}
```

Otherwise, the `castor.php` file of the plugin is never loaded by the projects
using it: it is the one of its own repository, to run the tasks you use while
working on it.

### The `castor-plugin` type

The `castor-plugin` type is for the repository of the plugin, where you run its
tasks while working on it: when there is no `castor.composer.json` file, Castor
uses the `composer.json` file of a package of this type instead. It installs the
packages of its `require` section in `.castor/vendor/`, locks them in a
`castor.composer.lock` file, and autoloads the files of the package from there,
as in a project requiring it. `castor composer` works on this file too.

Its `require-dev` section is left out: the tools you use to work on the package
run from the usual `vendor/` directory, locked by its `composer.lock` file.

`castor composer require` also uses this type, or the `extra.castor.features`
section, to know that a package is a plugin, and offer to register it.

## Migrating from `import()` and `mount()`

Before Castor 1.10, a package was loaded with `import('composer://vendor/package')`
or `mount('composer://vendor/package')`. Both are deprecated, and removed in
Castor 2.0:

| Before                                                     | After                                                                                         |
|------------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| `import('composer://vendor/package')`                      | `register_plugin('vendor/package')`                                                           |
| `import('composer://vendor/package', file: 'tasks.php')`   | `register_plugin('vendor/package', 'tasks')`, once the package declares a `tasks` feature     |
| `mount('composer://vendor/package', 'app')`                | `register_plugin('vendor/package', prefix: 'app')`                                            |

Until Castor 2.0, they keep their behavior: they load the `castor.php` file of
the package (or the `file` given), and `mount()` runs its tasks in the directory
of the package. `register_plugin()` does neither: a package relying on its
`castor.php` file declares it as its `default` feature.

> [!WARNING]
> The tasks, contexts and listeners of the files a package autoloads are still
> loaded when the package is not registered, with a deprecation: Castor 2.0
> will only load them when the package is registered.
