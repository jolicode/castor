## Helpers

Some helpers are built-in with Castor. You can retrieve them by type hinting
them in your function.

### SymfonyStyle

The `Symfony\Component\Console\Style\SymfonyStyle` class provides methods to
interact with the user and to display information:

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

## Filesystem

The `Symfony\Component\Filesystem\Filesystem` class provides OS-independent
utilities for filesystem operations and for file/directory paths manipulation.
You can also use static methods of the class
`Symfony\Component\Filesystem\Path`.

```php
use Symfony\Component\Filesystem\Filesystem;

#[AsTask]
function foo(Filesystem $fs)
{
    $dir = '/tmp/foo';

    echo $dir, ' directory exist: ', $fs->exists($dir) ? 'yes' : 'no', \PHP_EOL;

    $fs->mkdir($dir);
    $fs->touch($dir . '/bar.md');

    echo $dir, ' is an absolute path: ', Path::isAbsolute($dir) ? 'yes' : 'no', \PHP_EOL;
    echo '../ is an absolute path: ', Path::isAbsolute('../') ? 'yes' : 'no', \PHP_EOL;

    $fs->remove($dir);

    echo 'Absolute path: ', Path::makeAbsolute('../', $dir), \PHP_EOL;
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/components/filesystem.html)
for more information about this class and how to use it.
