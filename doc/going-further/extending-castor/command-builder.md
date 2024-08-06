# Creating a command builder

Some commands are complex and require a lot of parameters. To make it easier to 
create these commands, you can provide a command builder by using the 
`CommandBuiderInterface` to help users use the command without having to 
remember all parameters or options.

Here is an example of a command builder:

```php
class Ls implements CommandBuilderInterface
{
    private string $flags = '';

    public function __construct(private ?string $directory = null)
    {
    }

    public function all(): static
    {
        $this->flags .= 'a';

        return $this;
    }

    /** @return string[] */
    public function getCommand(): array
    {
        $command = ['ls'];

        if ($this->flags) {
            $command[] = '-' . $this->flags;
        }

        if ($this->directory) {
            $command[] = $this->directory;
        }

        return $command;
    }
}
```

You can use the command builder like this:

```php
use CommandBuilder\Ls;
use function Castor\run;

$ls = (new Ls('/path/to/directory'))->all();
run($ls);
```

Due to the philosophy of Castor, it is highly recommended to provide a function
that creates the command builder. Which provide a closer experience of castor 
way of doing things.

```php
use CommandBuilder\Ls;

function ls(string $directory): void
{
    return new Ls($directory);
}

...

run(ls('/path/to/directory')->all());

```

## Forcing the context of the command builder

Some commands may need a specific context for correct execution. By implementing
the `ContextUpdaterInterface` you can force the context of the command builder
right before the command is executed.

```php
class Ls implements CommandBuilderInterface, ContextUpdaterInterface
{
    ...

    public function updateContext(Context $context): Context
    {
        // Command will always be executed in /tmp
        return $context->withWorkingDirectory('/tmp');
    }
}
```
