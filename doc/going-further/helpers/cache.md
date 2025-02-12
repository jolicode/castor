# Cache

## The `cache()` function

Castor provides a `cache()` function to allow to cache items easily:

```php
use Castor\Attribute\AsTask;
use Psr\Cache\CacheItemInterface;

use function Castor\cache;
use function Castor\io;

#[AsTask()]
function foo()
{
    $result = cache('a-key', expansive_call(...));

    // Or if you want to set a TTL

    $result = cache('another-key', function (CacheItemInterface $item) => {
        $item->expiresAfter(3600);

        return expansive_call();
    });

    io()->writeln($result);
}
```

The `cache()` function prefix the key with a hash of the project directory to
avoid any collision between different Castor projects.

> [!TIP]
> To force the cached value to be recomputed, you can pass `true` to the `$force`
> parameter of the `cache()` function.

```php
// The expansive_call() function will be called even if the value is already in the cache
$result = cache('a-key', expansive_call(...), true);
```

> [!NOTE]
> Under the hood, Castor use Symfony Cache component. You can check the
> [Symfony documentation](https://symfony.com/doc/current/components/cache.html)
> for more information about this component and how to use it.

## Cache location on the filesystem

By default, Castor caches items on the filesystem, in the `<home directory>/.cache/castor`
directory. If you want to change the cache directory, you can set the `CASTOR_CACHE_DIR`
environment variable.

```shell
CASTOR_CACHE_DIR=/tmp/castor-cache castor foo
```

## The `get_cache()` function

If you need to have a full control on the cache, you can access the
`CacheItemPollInterface` directly with the `get_cache()` function:

```php
use Castor\Attribute\AsTask;

use function Castor\get_cache;
use function Castor\io;

#[AsTask()]
function foo()
{
    $cache = get_cache();

    $item = $cache->getItem('a-key');

    if (!$item->isHit()) {
      $item->set(expansive_call());
      $cache->save($item);
    }

    io()->writeln($item->get());
}
```
