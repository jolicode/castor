## Helpers

### SymfonyStyle

The `Symfony\Component\Console\Style\SymfonyStyle` class is a helper class
that provides methods to interact with the user and to display information.

You can use it by type hinting it in your function:

```php
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsTask]
function foo(SymfonyStyle $io): void
{
    $io->title('This is a title');

    $io->comment('With IO, you can ask questions ...');
    $value = $io->ask('Tell me something');
    $io->writeln('You said: ' . $value);

    $io->comment('... show progress bars ...');
    $io->progressStart(100);
    for ($i = 0; $i < 100; ++$i) {
        $io->progressAdvance();
        usleep(1000);
    }
    $io->progressFinish();

    $io->comment('... show table ...');
    $io->table(['Name', 'Age'], [
        ['Alice', 21],
        ['Bob', 42],
    ]);

    $io->success('This is a success message');
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/console/style.html)
for more information about this class and how to use it.
