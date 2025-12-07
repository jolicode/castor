---
description: >
  Learn the basic usage of Castor, including how to create and execute your
  first task, organize tasks into namespaces and files, and customize task
  attributes.
---

# Basic usage

This document covers the fundamental aspects of using Castor, from executing
your first task to organizing your project.

## Executing your first task

Castor uses a convention to find tasks. It will look for the first `castor.php`
file in the current directory or in parents directory.

In this file, all functions with the `#[Castor\Attribute\AsTask()]` attribute
will be transformed as tasks. The name of the function will be the task's name.

For example, if you have the following castor.php file:

```php
{% include "/examples/basic/usage/simple.php" %}
```

You can then execute your task by calling `castor` with the task's name:

```console
$ castor hello
Hello from castor.
```

Congratulations, you have just executed your first Castor task!

> [!NOTE]
> From now on, we will omit the leading `<?php` in all doc examples.

Once you have written your tasks, you can list them by simply running `castor`
without any arguments.

> [!NOTE]
> Castor will also look for a `.castor/castor.php` file in the current
> directory, or in its parents.
> The `castor.php` file has a higher priority than the `.castor/castor.php` file.
<!-- -->
> [!NOTE]
> You can also use the `--castor-file` option to specify a custom path to the
> root file. See the [related documentation](../going-further/interacting-with-castor/root-file-and-shebang.md).

## Creating a new Castor project

If you run `castor` in a directory not yet containing a `castor.php` file (or a
`.castor/castor.php` file), it will ask if you want to initialize a new project
by creating a `castor.php` file to help you get started.

To avoid the question, you can directly create the `castor.php` file by running
the `castor init` command.

## Namespaces as task namespaces

If your tasks are defined inside a PHP namespace, the task's name will be
prefixed by this namespace. If there are multiple levels of namespaces,
they will be joined with `:` to form the task's namespace.

Let's take this example:

```php
{% include "/examples/basic/usage/namespace.php" start="<?php\n\n" %}
```

Then you can run the task like this:

```console
$ castor usage:with:long:namespace:hello
Hello from castor.
```

If you need to, you can also define multiple tasks in different namespaces in
the same file:

```php
{% include "/examples/basic/usage/namespace_multiple.php" start="<?php\n\n" %}
```

You will then have two tasks: `foo1:hello1` and `foo2:hello2`.

## Splitting tasks in multiple files

Instead of putting all your tasks in a single `castor.php` file, you can split
them in multiple files for better organization.

### The `import()` function

You can use the `import()` function to import tasks from another file or
directory. This function takes a file path, or a directory as an argument.

When using a directory as an argument, Castor will load all the PHP files in it:

```php
{% include "/examples/basic/usage/import.php" start="<?php\n\nnamespace usage;\n\n" %}
```

> [!WARNING]
> You cannot dynamically import tasks. The `import()` function must be called
> at the top level of the file (i.e. not inside a function or a class).

### Using the `.castor/` directory

If you split your tasks in different files, we recommend to put them in a
directory named `.castor/` at the root of your project. This allows you to keep
your project organized and avoid cluttering the root directory.

You can also move the `castor.php` file inside the `.castor/` directory. It will
still be detected by Castor and just need to import the other files:

```php
{% include "/examples/basic/usage/dot_castor.php" start="<?php\n\n" %}
```

> [!NOTE]
> You can also import functions from a remote resource. See the
> [related documentation](../going-further/extending-castor/remote-imports.md).

## Overriding task name, namespace or description

The `Castor\Attribute\AsTask` attribute allows to customize the task. You can
override the task's name and namespace by providing the respective
arguments: `name`, `namespace`.

You can also set a description for the task by providing the `description`
argument.

For example:

```php
{% include "/examples/basic/usage/task_attribute.php" start="<?php\n\nnamespace usage;\n\n" %}
```

will appear like this in the task list:

```console
$ castor
...
Available commands:
  completion         Dump the shell completion script
  help               Display help for a command
  list               List commands
 foo
  foo:bar            Configures the default name, namespace, and description
```

## Setting a default task

The `Castor\Attribute\AsTask` attribute also allows you to set a default task.
A default task will be executed when you run `castor` without any arguments.

```php
{% include "/examples/basic/usage/default.php" start="<?php\n\nnamespace usage;\n\n" %}
```

> [!NOTE]
> When you define a default task, it will override the default behavior of
> displaying the tasks list. You can still access the tasks list by running
> `castor list`.
<!-- -->
> [!WARNING]
> You can only have one default task per Castor project.
