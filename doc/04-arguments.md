# Command arguments

When creating a function that will be used as a command, all the parameters of
the function will be used as arguments or options of the command:

```php
#[AsTask]
function command(
    string $firstArg,
    string $secondArg
) {
    run(['echo', $firstArg, $secondArg]);
}
```

Which can be called like that:

```bash
$ php castor.phar command foo bar
foo bar
```

## Optional arguments

You can make an argument optional by giving it a default value:

```php
#[AsTask]
function command(
    string $firstArg,
    string $default = 'default'
) {
    run(['echo', $firstArg, $secondArg]);
}
```

```bash
$ php castor.phar command foo
foo default
$ php castor.phar command --default=bar foo
foo bar
```

## Overriding the argument name and description

You can override the name and description of an argument by using
the `Castor\Attribute\AsArgument` attribute:

```php
#[AsTask]
function command(
    #[AsArgument(name: 'foo', description: 'This is the foo argument')]
    string $arg = 'bar',
) {
    run(['echo', $arg]);
}
```

```bash
$ php castor.phar command foo
foo
```

## Overriding the option name and description

If you prefer, you can force an argument to be an option by using the
`Castor\Attribute\AsOption` attribute:

```php
#[AsTask]
function command(
    #[AsOption(name: 'foo', description: 'This is the foo option')]
    string $arg = 'bar',
) {
    run(['echo', $arg]);
}
```

```bash
$ php castor.phar command --foo=foo
foo
```

You can also configure the `mode` of the option. The `mode` determines how the
option must be configured:

```php
#[AsTask]
function command(
    #[AsOption(description: 'This is the foo option', mode: InputOption::VALUE_NONE)]
    bool $force,
) {
    if ($force) {
        echo "command has been forced\n";
    }
}
```

```bash
$ php castor.phar command --force
command has been forced
```

---

Please refer to the [Symfony
documentation](https://symfony.com/doc/current/console/input.html#using-command-options)
for more information.
