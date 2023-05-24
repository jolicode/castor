## Helpers

### SymfonyStyle

The `Symfony\Component\Console\Style\SymfonyStyle` class is a small helper class that provides methods to interact with the user and to display information.
You can use it by type hinting it in your function.

```php
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsTask]
function foo(SymfonyStyle $io): void
{
    $value = $io->ask('Tell me something');
    $io->success('You said: ' . $value);
}
```

You can check the [Symfony documentation](https://symfony.com/doc/current/console/style.html) for more information about
this class and how to use it.

