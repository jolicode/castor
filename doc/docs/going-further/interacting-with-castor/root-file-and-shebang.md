---
description: >
  Learn how to use a custom root file and shebang lines in Castor to create
  directly executable scripts for your tasks.
---

# Root file and shebang

This document explains how to make Castor to use a custom root file and
how to leverage shebang lines for direct script execution.

## Using a custom root file

By default, castor looks for a `castor.php` or  `.castor/castor.php` file in the
current working directory or one of its parents.

However, you can use another root file by using the `--castor-file` option:

```bash
castor --castor-file=path/to/your-file.php your-task
```

The root directory then follows that file instead of the directory you started
Castor from, so its own [remote imports](../extending-castor/remote-imports.md)
are read from the `castor.composer.json` sitting next to it, and installed in its
own `.castor/vendor`. When the file lives in a `.castor` directory, the root is
the directory holding it, just like for a regular `.castor/castor.php`.

The working directory of the [context](../../getting-started/context.md) is left
alone: the tasks still run where you started Castor from. Pointing at another
entrypoint tells Castor where to read the tasks from, not where to run them.

## Using a shebang line

Unix systems support shebang lines to execute scripts directly from the command
line, without having to prefix them with the interpreter.

The `--castor-file` option makes it possible to create a Castor file that can be
executed directly. For example, you can create a file named `my-script` with the
following content:

```php
{% include "/examples/advanced/castor-file/shebang.php" %}
```

Make sure to give execute permissions to your script:

```bash
chmod +x my-script
```

Now, you can run your script directly from the command line:

```bash
./my-script shebang-task
```
