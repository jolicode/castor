# Using .env files

## The `load_dot_env()` function

You can load a `.env` file with the `load_dot_env()` function. This will:

- load the `.env` file
- populate the env variables for the current process
- return the env variables as key/value array.

> [!NOTE]
> By default, it loads the `.env` file on your project root (where castor file
> or folder was found), but you can overload this by passing your `.env` file
> path as an argument.

Example:

```php
use Castor\Attribute\AsTask;
use Castor\Context;

use function Castor\load_dot_env;

#[AsTask()]
function show_database_url(): void
{
    $env = load_dot_env();

    io()->writeln($_SERVER['DATABASE_URL'] ?? throw new \RuntimeException('DATABASE_URL is not defined'));
}
```

> [!IMPORTANT]
> Even if the `load_dot_env()` function returns the env variables as an array,
> it will only return variables defined in the .env file. So if someone overrides
> one variable with true environment variable, it will not be returned. So it's
> better to use `$_SERVER` in you code to access env variables.

> [!NOTE]
> You can find more about how `.env` file loading and overloading works on
> [related Symfony documentation](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-env-files).

## Create a context from a .env file

You can also create a context that load a `.env` file:

```php
use Castor\Attribute\AsContext;
use Castor\Context;

use function Castor\load_dot_env;

#[AsContext()]
function my_context(): Context
{
    return new Context(load_dot_env());
}
```
