# Task arguments

When creating a function that will be used as a task, all the parameters of
the function will be used as arguments or options:

```php
{% include "/examples/basic/arguments/simple.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

Which can be called like that:

```console
$ castor simple foo bar
foo bar
```

## Optional arguments

You can make an argument optional by giving it a default value:

```php
{% include "/examples/basic/arguments/optional.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

```console
$ castor optional foo
foo default
$ castor optional foo --second-arg=bar
foo bar
```

## Arguments without configuration nor validation

By default, Castor will validate the arguments and options provided to a task.
Thus it will emit an error if an unknown argument or option is provided or if an
required argument is missing:

```console
$ castor simple foo --unknown-option value
                                                 
  The "--unknown-option" option does not exist.  
                                                 
```

If you want to disable this validation for a specific task, you can use the
`ignoreValidationErrors` flag:

```php
{% include "/examples/basic/arguments/no-validation.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

Castor will not emit validation errors for unknown arguments or options when
running this task:

```bash
castor no-validation --unknown-option value
```

But instead of simply disabling validation, you may also want to capture the raw
arguments provided to the task and pass them to a sub process.
You can do so by using the `#[AsRawTokens]()` attribute instead:

```php
{% include "/examples/basic/arguments/raw-tokens.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

Then, you can use it like that:

```bash
castor phpunit --filter=testName --debug --verbose
```

## Overriding the argument name and description

You can override the name and description of an argument by using
the `#[Castor\Attribute\AsArgument()]` attribute:

```php
{% include "/examples/basic/arguments/overriding-argument.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

```console
$ castor override-argument --help
Usage:
  arguments:override-argument [<foo>]

Arguments:
  foo                         This is the foo argument [default: "bar"]

```

## Overriding the option name and description

If you prefer, you can force an argument to be an option by using the
`#[Castor\Attribute\AsOption()]` attribute:

```php
{% include "/examples/basic/arguments/overriding-option.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

```php
$ castor override-option --help
Usage:
  arguments:override-option [options]

Options:
      --foo[=FOO]           This is the foo option [default: "bar"]

```

```console
$ castor override-option --foo=foo
foo
```

You can also configure the `mode` of the option. The `mode` determines how the
option will be registered (no value expected, required, optional, negatable, etc):

```php
{% include "/examples/basic/arguments/option-mode.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

```console
$ castor option-mode --force
Command has been forced.
```

Please refer to the [Symfony
documentation](https://symfony.com/doc/current/console/input.html#using-command-options)
for more information about option modes.

## Path arguments and options

In some cases, you may want the user to provide a path in an argument or an option.
In order to ease the use of paths for users, Castor provides the `#[AsPathArgument()]`
and `#[AsPathOption()]` attributes alternatives to `#[AsArgument()]` and `#[AsOption]()`.

When using `#[AsPathArgument()]` or `#[AsPathOption()]`, the argument or option
will be autocompleted with suggestions of paths.

```php
{% include "/examples/basic/arguments/path-argument.php" start="<?php\n\nnamespace arguments;\n\n" %}
```

```console
$ castor path-argument /var/www/[TAB]
/var/www/foo  /var/www/bar  /var/www/baz
```

See the [autocompletion documentation](../going-further/interacting-with-castor/autocomplete.md) for more information about completion.
