## Exec function

Castor provides a `Castor\exec` function to execute commands. It allows to run a sub process and execute whatever you want.

```php
<?php

use Castor\Attribute\AskTask;
use function Castor\exec;

#[AskTask]
function foo(): void
{
    exec('echo "bar"');
    exec(['echo', 'bar']);
}
```

You can pass a string or an array of string for this command, when using a string, it will use the shell to execute the command.

### Process object

Under the hood, Castor uses the `Symfony\Component\Process\Process` object to execute the command. The `exec` function will return
this object. So you can use the API of this object to interact with the process.

```php
#[AskTask]
function foo(): void
{
    $process = exec('echo "bar"');
    $process->isSuccessful(); // will return true
}
```

### Failure

By default, Castor will throw an exception if the command fails. You can disable that by setting the `allowFailure` option to `true`.

```php
#[AskTask]
function foo(): void
{
    exec('a_command_that_does_not_exist', allowFailure: true);
}
```

### Working directory

By default, Castor will execute the command in the same directory as the `.castor.php` file. You can change that by setting the
`path` argument. Which can be either a relative or an absolute path.

```php
#[AskTask]
function foo(): void
{
    exec('pwd', path: '../'); // execute the command in the parent directory of the .castor.php file
    exec('pwd', path: '/tmp'); // execute the command in the /tmp directory
}
```

### Environment variables

By default, Castor will use the same environment variables as the current process. You can add or override environment variables
by setting the `environment` argument.

```php
#[AskTask]
function foo(): void
{
    exec('echo $FOO', environment: ['FOO' => 'bar']); // will print "bar"
}
```

### Processing the output

By default, Castor will forward the stdout and stderr to the current terminal.
If you do not want to print the output of the command you can set the `quiet` option to `true`.

```php
#[AskTask]
function foo(): void
{
    exec('echo "bar"', quiet: true); // will not print anything
}
```

You can also fetch the output of the command by using the API of the `Symfony\Component\Process\Process` object.

```php
#[AskTask]
function foo(): void
{
    $process = exec('echo "bar"', quiet: true); // will not print anything
    $output = $process->getOutput(); // will return "bar\n"
}
```

### Pty & Tty

By default, Castor will use a pseudo terminal (pty) to execute the command, which allows to have nice output in most cases.
For some commands you may want to disable the pty and use a tty instead. You can do that by setting the `tty` option to `true`.

```php
#[AskTask]
function foo(): void
{
    exec('echo "bar"', tty: true);
}
```

> :warning: When using a tty, the output of the command will not be available in the process object.

You can also disable the pty by setting the `pty` option to `false`. In this mode no input will be used also.

```php

#[AskTask]
function foo(): void
{
    exec('echo "bar"', pty: false);
}
```
