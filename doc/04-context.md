## Context

For every command that castor execute, it uses a `Context` object. This object
contains the default values for the `exec` or `watch` function (directory,
environment variables, pty, tty, etc...).

It also contains custom values that can be set by the user and reused in
commands.

The context is immutable, which means that every time you change a value, a new
context is created.

### Using the context

The context is passed to the function if it has an argument type-hinted
with `Castor\Context`:

```php
use Castor\Context;

#[AsTask]
function foo(Context $context): void
{
    echo $context->currentDirectory; // will print the directory of the .castor.php file
    $context = $context->withPath('/tmp'); // will create a new context with the current directory set to /tmp
    exec('pwd', context: $context); // will print /tmp
}
```

### Creating a new context

You can create a new context by declaring a function with
the `Castor\Attribute\AsContext` attribute:

```php
use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext]
function my_context(): Context
{
    return new Context(environment: ['FOO' => 'BAR']);
}

#[AsTask]
function foo(): void
{
    exec('echo $FOO');
}
```

By default the `foo` command will not print anything as the `FOO` environment
variable is not set. If you want to use your new context you can use
the `--context` option:

```bash
$ php castor.phar foo

$ php castor.phar foo --context=my-context
BAR
```

> **Note**
> You can override the context name by setting the `name` argument of the
> `AsContext` attribute.

### Setting a default context

You may want to set a default context for all your commands. You can do that by
setting the `default` argument to `true` in the `AsContext` attribute:

```php
use Castor\Attribute\AsContext;
use Castor\Context;

#[AsContext(default: true, name: 'my_context')]
function create_default_context(): Context
{
    return new Context(['foo' => 'bar'], currentDirectory: '/tmp');
}

#[AsTask]
function foo(Context $context): void
{
    exec(['echo', $context['foo']]); // will print bar even if you do not use the --context option
    exec('pwd'); // will print /tmp
}
```
