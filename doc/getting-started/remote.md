# Remote execution

Castor allows you to run any php package without installing it on your machine or
withing your dependencies. This is done by using the `castor execute` command

As an example, let's say you want to run the `friendsofphp/php-cs-fixer` package
to fix your code style. You can do this by running the following command:

```bash
castor execute friendsofphp/php-cs-fixer fix
```

This will download the package and its dependencies, and then run the first
binary command it finds in the package. In this case, it will run the `php-cs-fixer`
binary command with the `fix` argument.

All options after the package name will be passed to the binary command.

## Specific binary of package

If you want to run a specific binary command of a package, you can do this by
adding the binary name after the package name separated with the `@` character :

```bash
castor execute friendsofphp/php-cs-fixer@php-cs-fixer fix
```

## Using a specific version of a package

If you want to run a specific version of a package, you can do this by adding the
version number after the package name separated with the `:` character :

```bash
castor execute friendsofphp/php-cs-fixer:3.0 fix
```

## Extra dependencies

You may need several packages to run a command. For example, if you want to run
phpstan with extensions, you can do this by running the following command:

```bash
castor execute --deps phpstan/phpstan-symfony phpstan/phpstan
```

## More memory

If the script requires more memory than the default provided by PHP or castor static binary,
you can use the `CASTOR_MEMORY_LIMIT` environment variable to increase the memory limit within the
context:

```bash
CASTOR_MEMORY_LIMIT=512M castor execute friendsofphp/php-cs-fixer fix
```

`CASTOR_MEMORY_LIMIT` supports the same values as the [`memory_limit` directive
in PHP](https://www.php.net/manual/en/ini.core.php#ini.memory-limit).
