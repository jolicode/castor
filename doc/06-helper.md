# Helpers

Some helpers are built-in with Castor. They allow you to:

* play with input / output
* interact with the filesystem
* search for files in a directory hierarchy
* cache things
* retrieve console related objects
* and more

## SymfonyStyle

The `io()` returns an object that provides methods to interact with the user and
to display information. It returns an instance of
`Symfony\Component\Console\Style\SymfonyStyle`:

```php
use Castor\Attribute\AsTask;

use function Castor\io;

#[AsTask]
function foo(): void
{
    io()->title('This is a title');

    io()->comment('With IO, you can ask questions ...');
    $value = io()->ask('Tell me something');
    io()->writeln('You said: ' . $value);

    io()->comment('... show progress bars ...');
    io()->progressStart(100);
    for ($i = 0; $i < 100; ++$i) {
        io()->progressAdvance();
        usleep(1000);
    }
    io()->progressFinish();

    io()->comment('... show table ...');
    io()->table(['Name', 'Age'], [
        ['Alice', 21],
        ['Bob', 42],
    ]);

    io()->success('This is a success message');
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/console/style.html)
for more information about this class and how to use it.

## Filesystem

The `fs()` function returns an object that provides OS-independent utilities for
filesystem operations and for file/directory paths manipulation. It returns an
instance of `Symfony\Component\Filesystem\Filesystem`.

You can also use static methods of the class
`Symfony\Component\Filesystem\Path`:

```php
use Castor\Attribute\AsTask;
use Symfony\Component\Filesystem\Path;

use function Castor\fs;

#[AsTask]
function foo()
{
    $dir = '/tmp/foo';

    echo $dir, ' directory exist: ', fs()->exists($dir) ? 'yes' : 'no', \PHP_EOL;

    fs()->mkdir($dir);
    fs()->touch($dir . '/bar.md');

    echo $dir, ' is an absolute path: ', Path::isAbsolute($dir) ? 'yes' : 'no', \PHP_EOL;
    echo '../ is an absolute path: ', Path::isAbsolute('../') ? 'yes' : 'no', \PHP_EOL;

    fs()->remove($dir);

    echo 'Absolute path: ', Path::makeAbsolute('../', $dir), \PHP_EOL;
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/components/filesystem.html)
for more information about this component and how to use it.

## Finder

The `finder()` function returns an object that finds files and directories based
on different criteria (name, file size, modification time, etc.) via an
intuitive fluent interface. It returns an instance of
`Symfony\Component\Finder\Finder`:

```php
use Castor\Attribute\AsTask;

use function Castor\finder;

#[AsTask]
function foo()
{
    echo 'Number of PHP files: ', finder()->name('*.php')->in(__DIR__)->count(), \PHP_EOL;
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/components/finder.html)
for more information about this class and how to use it.

## HTTP requests

The `request()` function allows to make HTTP requests easily. It performs HTTP
request and returns an instance of
`Symfony\Contracts\HttpClient\ResponseInterface`:

```php
use Castor\Attribute\AsTask;

use function Castor\request;

#[AsTask]
function foo()
{
    echo request('GET', 'https://example.org')->getContent(), \PHP_EOL;
}
```

If you need to have a full control on the HTTP client, you can access the
`HttpClientInterface` directly with the `http_client()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\http_client;

#[AsTask]
function foo()
{
    $client = http_client()
        ->withOptions([
            'verify_peer' => false,
            'timeout' => 10,
        ])
    ;
}
```

If you need to configure the HTTP client globally, you can do it in the context
creator:

```php
use Castor\Attribute\AsContext;
use Castor\GlobalContext;
use Castor\Context;

#[AsContext(name: 'preprod')]
function preprodContext(): Context
{
    // $client = ...
    GlobalContext::setHttpClient($client)

    //return new Context(...);
}
```

You can check
the [Symfony documentation](https://symfony.com/doc/current/http_client.html)
for more information about this component and how to use it.

## Cache

The `cache()` function allow to cache items:

```php
use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;

use function Castor\cache;

#[AsTask]
function foo()
{
    echo cache('a-key', expansive_call(...));

    // Or if you want to set a TTL

    echo cache('another-key', function (CacheItemInterface $item) => {
        $item->expiresAfter(3600);

        return expansive_call();
    });
}
```

By default it caches items on the filesystem, in the `/tmp/castor` directory.
The function also prefix the key with a hash of the project directory to avoid
any collision between different project.

If you need to have a full control on the cache, you can access the
`CacheItemPollInterface` directly with the `get_cache()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\get_cache;

#[AsTask]
function foo()
{
    $cache = get_cache();

    $item = $cache->getItem('a-key');

    if (!$item->isHit()) {
      $item->set(expansive_call());
      $cache->save($item);
    }

    echo $item->get();
}
```

If you need to configure the cache storage, you can do it in the context creator:
```php
use Castor\Attribute\AsContext;
use Castor\GlobalContext;
use Castor\Context;
use Castor\PathHelper;

#[AsContext(name: 'preprod')]
function preprodContext(): Context
{
    // $cache = ...
    GlobalContext::setCache($cache)

    //return new Context(...);
}
```

Under the hood, castor use Symfony Cache component. You can check
the [Symfony documentation](https://symfony.com/doc/current/components/cache.html)
for more information about this component and how to use it.

## Console related helpers

There are some low level helpers to access internal stuff:

* `app()` returns the current
  [`Application`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Application.php)
* `task()` returns the running task
  [`Command`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Command/Command.php)
* `input()` returns the current
  [`Input`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Output/OutputInterface.php)
* `output()` returns the current
  [`Output`](https://github.com/symfony/symfony/blob/6.3/src/Symfony/Component/Console/Input/InputInterface.php)

## Load a .env file

You can load a `.env` file with the `load_dot_env()` function:

```php
use Castor\Attribute\AsTask;
use Castor\Context;
use function Castor\load_dot_env;

#[AsTask]
function show_database_url(): void
{
    $env = load_dot_env();

    echo $env['DATABASE_URL'] ?? throw new \RuntimeException('DATABASE_URL is not defined');
}
```

And you can also create a context that load a `.env` file:

```php
use Castor\Attribute\AsContext;
use Castor\Context;
use function Castor\load_dot_env;

#[AsContext]
function my_context(): Context
{
    return new Context(load_dot_env());
}
```

By default, it loads the `.env` file on your project root (where castor file or
folder was found), but you can overload this by passing your `.env` file
path as an argument.
You can find more about how `.env` file loading and overloading works on
[related Symfony documentation](https://symfony.com/doc/current/configuration.html#configuring-environment-variables-in-env-files).

## Other helpers

* `context()` returns the initial `Context`. See the [context
  documentation](./05-context.md) for mor information
* `variable()` returns a variable stored in the  `Context`. See the [context
  documentation](./05-context.md) for mor information
