# Task arguments

When creating a function that will be used as a task, all the parameters of
the function will be used as arguments or options:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function task(
    string $firstArg,
    string $secondArg
) {
    io()->writeln($firstArg . ' ' . $secondArg);
}
```

Which can be called like that:

```bash
$ castor task foo bar
foo bar
```

## Optional arguments

You can make an argument optional by giving it a default value:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function task(
    string $firstArg,
    string $secondArg = 'default'
) {
    io()->writeln($firstArg . ' ' . $secondArg);
}
```

```bash
$ castor task foo
foo default
$ castor task foo --second-arg=bar
foo bar
```

## Arguments without configuration nor validation

Castor supports the use of arguments without any configuration nor validation.
For example, when you want to call a sub-process:

```php
#[AsTask()]
function phpunit(#[AsRawTokens] array $rawTokens): void
{
    run(['phpunit', ...$rawTokens]);
}
```

Then, you can use it like that:

```bash
$ castor phpunit --filter=testName --debug --verbose
```

You can also disable validation by using the `ignoreValidationErrors` flag:

```php
#[AsTask(ignoreValidationErrors: true)]
function do_something(): void
{
}
```

> [!TIP]
> Related example: [args.php](https://github.com/jolicode/castor/blob/main/examples/args.php)

## Overriding the argument name and description

You can override the name and description of an argument by using
the `Castor\Attribute\AsArgument` attribute:

```php
use Castor\Attribute\AsArgument;
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function command(
    #[AsArgument(name: 'foo', description: 'This is the foo argument')]
    string $arg = 'bar',
) {
    io()->writeln($arg);
}
```

```bash
$ castor command foo
foo
```

> [!TIP]
> Related example: [args.php](https://github.com/jolicode/castor/blob/main/examples/args.php)

## Overriding the option name and description

If you prefer, you can force an argument to be an option by using the
`Castor\Attribute\AsOption` attribute:

```php
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask()]
function command(
    #[AsOption(name: 'foo', description: 'This is the foo option')]
    string $arg = 'bar',
) {
    io()->writeln($arg);
}
```

```bash
$ castor command --foo=foo
foo
```

You can also configure the `mode` of the option. The `mode` determines how the
option must be configured:

```php
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\run;

#[AsTask()]
function command(
    #[AsOption(description: 'This is the foo option', mode: InputOption::VALUE_NONE)]
    bool $force,
) {
    if ($force) {
        io()->writeln('command has been forced');
    }
}
```

```bash
$ castor command --force
command has been forced
```

> [!TIP]
> Related example: [args.php](https://github.com/jolicode/castor/blob/main/examples/args.php)

---

Please refer to the [Symfony
documentation](https://symfony.com/doc/current/console/input.html#using-command-options)
for more information.

## Path arguments and options

In some cases, you may want the user to provide a path in an argument or an option.
In order to ease the use of paths for users, Castor provides the `AsPathArgument`
and `AsPathOption` attributes alternatives to `AsArgument` and `AsOption`.

When using `AsPathArgument` or `AsPathOption`, the argument or option will be
autocompleted with suggestions of paths.

```php
use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;

use function Castor\run;
#[AsTask()]
function command(
    #[AsPathArgument()]
    string $argument,
): void {
}
```

```bash
$ castor command /var/www/[TAB]
/var/www/foo  /var/www/bar  /var/www/baz
```

See the [autocompletion documentation](../going-further/interacting-with-castor/autocomplete.md) for more information about completion.
