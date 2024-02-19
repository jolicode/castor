# Re-use Symfony Command

If your Castor project lives near a Symfony application, you may want to use
some of its commands directly as Castor tasks.
This is possible with the `AsSymfonyTask` attribute you can set on your
command class.

> [!NOTE]
> Thanks to how PHP attributes works, your application will work even if it
> does not find this attribute class (which will probably not be available
>  on your vendor directory - unless you installed Castor with Composer).

```php

use Castor\Attribute\AsSymfonyTask;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Commhearand\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('hello', 'Says hello from a Symfony application')]
#[AsSymfonyTask(name: 'symfony:hello')]
class HelloCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return 0;
    }
}
```

By default, the attribute will re-use the same name as the one defined in the
Symfony application, but you can override it with the `name` parameter.

If Symfony command does own a `AsCommand` attribute, you must set the
`originName` parameter, and it must be the same as the same in the symfony
application.

And finally, you can give a way to access the Symfony application entry point
with the `console` parameter. Some examples:

* `['bin/console']`, this is the default, when Symfony and Castor live in the
  very same directory
* `['path/to/symfony/bin/console']`, when Symfony is in another directory
* `['docker', 'exec', 'foobar-backend-1', '/app/server/backend/bin/console']`, when
  your Symfony application lives in a docker container
