---
description: >
  Learn how to use Castor's `mount()` function to integrate multiple Castor
  applications, manage working directories, and organize tasks with namespaces.
---

# Mount another application

Castor can mount another application. This pattern is useful when you have a
majestic monolith and you want to split it into smaller applications.

## Introduction

Let's take the following projects architecture example:

```text
projects/
├─ A/
│  ├─ castor.php
├─ B/
│  ├─ castor.php
castor.php
```

In this example, `projects/A/castor.php` and `projects/B/castor.php` are two
standalone castor applications. All functions defined in `projects/A/castor.php`
will be run **inside** the `projects/A` directory whatever your current directory
is.

To achieve this, you need to use the `mount()` function in your main
`castor.php`:

```php
use function Castor\mount;

mount('projects/A');
mount('projects/B');
```

You can also prefix a namespace to all task found in each mounted application:

```php
use function Castor\mount;

mount('projects/A', 'project:a');
mount('projects/B', 'project:b');
```

A mounted application can have its own `castor.composer.json` file (see
[remote imports](./remote-imports.md)): its packages are installed in its own
`.castor/vendor/` directory, and loaded with it, as when it runs on its own.

## `mount()` vs `import()`

You may wonder when to use `mount()` vs `import()`. This really depends on the
working directory. If in your `path/to/another/castor/app` you keep repeating the
kind of code:

```php
use Castor\Attribute\AsTask;

use function Castor\context;
use function Castor\run;

#[AsTask()]
function foobar() {
    run($command, context: context()->withWorkingDirectory(__DIR__));
}
```

Then you should use `mount()` to avoid repeating the `workingDirectory`
argument.

Otherwise, you can continue to use `import()`.

> [!NOTE]
> `mount()` and `import()` only load local files and directories. To use the
> tasks of a Composer package, [register it as a plugin](plugins.md): a plugin
> uses `__DIR__` for its own files, so its tasks run in the working directory of
> the context. `mount()` stays useful for code that does not know it is loaded
> by another application.
