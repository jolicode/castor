# Cache

## The `cache()` function

Castor provides a `cache()` function to allow to cache items easily:

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

By default, it caches items on the filesystem, in the `<home directory>/.cache/castor`
directory. The function also prefix the key with a hash of the project directory
to avoid any collision between different Castor projects.

> [!NOTE]
> Under the hood, Castor use Symfony Cache component. You can check the
> [Symfony documentation](https://symfony.com/doc/current/components/cache.html)
> for more information about this component and how to use it.

## The `get_cache()` function

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
