<?php

namespace Castor\Llm;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The value configured with castor:llm:configure, for the project or for all
 * of them. It lives in the cache of Castor, like the fingerprints: it is a
 * preference of the user on this machine, not something to share with the
 * project.
 *
 * @internal
 */
class ConfigStorage
{
    private const KEY = 'llm-config';

    public function __construct(
        private readonly CacheItemPoolInterface&CacheInterface $cache,
        private readonly string $rootDir,
    ) {
    }

    public function get(bool $global): ?string
    {
        $item = $this->cache->getItem($this->getKey($global));

        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();

        return \is_string($value) && '' !== $value ? $value : null;
    }

    public function set(string $value, bool $global): void
    {
        $item = $this->cache->getItem($this->getKey($global));
        $item->set($value);

        $this->cache->save($item);
    }

    public function remove(bool $global): void
    {
        $this->cache->deleteItem($this->getKey($global));
    }

    private function getKey(bool $global): string
    {
        if ($global) {
            return self::KEY;
        }

        return \sprintf('%s-%s', hash('xxh128', $this->rootDir), self::KEY);
    }
}
