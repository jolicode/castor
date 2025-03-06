# Executing a phar file

## The `run_php()` function

The `run_php()` function provides a way to execute a php or phar file in all scenarios,
whether castor is executed as a phar, as a static binary, or as a script.

```php
#[AsTask()]
function exec_something()
{
    run_php('path/to/my.phar', ['arg1', 'arg2']);
}
```

This allow to execute external php script even if you don't have PHP when using
the static binary and without conflicts between the external script and internal
php code of Castor.

The `run_php()` takes exactly the same options as the `run()` function.

### Script requiring more memory

If you need to execute a script that requires more memory than the default
provided by PHP or castor static binary, you can use the `CASTOR_MEMORY_LIMIT` 
environment variable to increase the memory limit within the context: 

```php
#[AsTask()]
function exec_something()
{
    run_php('path/to/my.phar', ['arg1', 'arg2'], context: context()->withEnvironment(['CASTOR_MEMORY_LIMIT' => '512M']));
}
```

`CASTOR_MEMORY_LIMIT` supports the same values as the [`memory_limit` directive in PHP](https://www.php.net/manual/fr/ini.core.php#ini.memory-limit).
