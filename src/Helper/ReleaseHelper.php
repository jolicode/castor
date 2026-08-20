<?php

namespace Castor\Helper;

use JoliCode\PhpOsHelper\OsHelper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** @internal */
class ReleaseHelper
{
    public function __construct(
        private readonly CacheItemPoolInterface&CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly Installation $installation,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Returns the latest GitHub release payload, or null if it could not be fetched.
     *
     * The result is cached for a day (10 minutes on failure).
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(bool $useCache = true, float $timeout = 1): ?array
    {
        $item = $this->cache->getItem('castor-releases');

        if ($item->isHit() && $useCache) {
            return $item->get();
        }

        $latestVersion = null;
        $item->expiresAfter(60 * 60 * 24);

        try {
            $latestVersion = $this
                ->httpClient
                ->request('GET', 'https://api.github.com/repos/jolicode/castor/releases/latest', [
                    'timeout' => $timeout,
                ])
                ->toArray()
            ;
        } catch (ExceptionInterface) {
            $this->logger->info('Failed to fetch latest Castor version from GitHub.');

            $item->expiresAfter(60 * 10);
        }

        $this->cache->save($item->set($latestVersion));

        return $latestVersion;
    }

    /**
     * Returns the download URL of the release asset matching the current OS,
     * architecture and installation method (phar or static binary).
     *
     * @param array<string, mixed> $release
     */
    public function getDownloadUrl(array $release): ?string
    {
        $assets = $release['assets'] ?? [];

        $assets = match (true) {
            OsHelper::isWindows() || OsHelper::isWindowsSubsystemForLinux() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'windows')),
            OsHelper::isMacOS() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'darwin')),
            OsHelper::isUnix() => array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], 'linux')),
            default => [],
        };

        $architecture = $this->installation->getArchitecture();
        $assets = array_filter($assets, static fn (array $asset): bool => str_contains((string) $asset['name'], $architecture->value));

        if (InstallationMethod::Static === $this->installation->getMethod()) {
            $assets = array_filter($assets, static fn (array $asset): bool => !str_ends_with((string) $asset['name'], '.phar'));
        } else {
            $assets = array_filter($assets, static fn (array $asset): bool => str_ends_with((string) $asset['name'], '.phar'));
        }

        return array_first($assets)['browser_download_url'] ?? null;
    }
}
