# Executing a phar file

## The `run_phar()` function

The `run_phar()` function provides a way to execute a phar file in all scenarios,
whether castor is executed as a phar, as a static binary, or as a script.

```php
#[AsTask()]
function exec_something()
{
    run_phar('path/to/my.phar', ['arg1', 'arg2']);
}
```

This allow to execute external php script even if you don't have PHP when using
the static binary and without conflicts between the external script and internal
php code of Castor.

The `run_phar()` takes exactly the same options as the `run()` function.
