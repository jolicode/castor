## Command arguments

When creating a function that will be used as a command, all the parameters of the function will be used as arguments of the command.

```php
#[AsTask]
function command(
    string $firstArg,
    string $secondArg
) {
    exec(['echo', $firstArg, $secondArg]);
}
```

Which can be called like that:

```bash
$ php castor.phar command foo bar
foo bar
```

### Optional arguments

You can make an argument optional by giving it a default value.

```php
#[AsTask]
function command(
    string $firstArg,
    string $default = 'default'
) {
    exec(['echo', $firstArg, $secondArg]);
}
```
```bash
$ php castor.phar command foo
foo default
$ php castor.phar command --default=bar foo
foo bar
```

### Overriding the argument name and description

You can override the name and description of an argument by using the `Castor\Attribute\AsArgument` attribute:

```php
#[AsTask]
function command(
    #[AsArgument(name: 'foo', description: 'This is the foo argument')]
    string $arg = 'bar',
) {
    exec(['echo', $arg]);
}
```
```bash
$ php castor.phar command --foo=foo
foo
```
