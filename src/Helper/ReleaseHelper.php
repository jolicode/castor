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
final readonly class ReleaseHelper
{
    /** The rolling pre-release of the main branch, see the Artifacts workflow */
    public const string SNAPSHOT_TAG = 'snapshot';

    /** Release asset listing the SHA-256 checksum of every other asset */
    public const string CHECKSUMS_FILE = 'SHA256SUMS';

    private const string API_URL = 'https://api.github.com/repos/jolicode/castor/releases';

    public function __construct(
        private CacheItemPoolInterface&CacheInterface $cache,
        private HttpClientInterface $httpClient,
        private Installation $installation,
        private LoggerInterface $logger = new NullLogger(),
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
        return $this->fetch('/latest', 'castor-releases', $useCache, $timeout);
    }

    /**
     * Returns the GitHub release payload of the given tag, or null if it could
     * not be fetched. Same caching as getLatest().
     *
     * @return array<string, mixed>|null
     */
    public function getRelease(string $tag, bool $useCache = true, float $timeout = 1): ?array
    {
        return $this->fetch('/tags/' . $tag, 'castor-release-' . $tag, $useCache, $timeout);
    }

    /**
     * Returns the download URL of the release asset matching the current OS,
     * architecture and installation method (phar or static binary).
     *
     * @param array<string, mixed> $release
     */
    public function getDownloadUrl(array $release): ?string
    {
        return $this->getDownloadAsset($release)['browser_download_url'] ?? null;
    }

    /**
     * Returns the release asset matching the current OS, architecture and
     * installation method (phar or static binary).
     *
     * Its "url" is the API URL of the asset: unlike "browser_download_url",
     * it is not cached by name by the GitHub CDN, which matters for the
     * snapshot whose assets are replaced on each push.
     *
     * @param array<string, mixed> $release
     *
     * @return array<string, mixed>|null
     */
    public function getDownloadAsset(array $release): ?array
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

        return array_first($assets);
    }

    /**
     * Returns the asset holding the SHA-256 checksums of all the release assets,
     * or null for releases published without it.
     *
     * @param array<string, mixed> $release
     *
     * @return array<string, mixed>|null
     */
    public function getChecksumsAsset(array $release): ?array
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if (self::CHECKSUMS_FILE === ($asset['name'] ?? null)) {
                return $asset;
            }
        }

        return null;
    }

    /**
     * Returns the expected SHA-256 checksum of the given asset, as listed in
     * the SHA256SUMS file (one "<checksum>  <file name>" line per asset).
     *
     * @throws \RuntimeException when the file has no entry for the asset
     */
    public function getExpectedChecksum(string $checksumsFileContent, string $assetName): string
    {
        foreach (preg_split('/\R/', $checksumsFileContent) ?: [] as $line) {
            if (preg_match('/^(?<checksum>[0-9a-f]{64})\s+\*?(?<name>.+)$/i', trim($line), $matches) && $matches['name'] === $assetName) {
                return strtolower($matches['checksum']);
            }
        }

        throw new \RuntimeException(\sprintf('The %s file has no entry for "%s".', self::CHECKSUMS_FILE, $assetName));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(string $path, string $cacheKey, bool $useCache, float $timeout): ?array
    {
        $item = $this->cache->getItem($cacheKey);

        if ($item->isHit() && $useCache) {
            return $item->get();
        }

        $release = null;
        $item->expiresAfter(60 * 60 * 24);

        try {
            $release = $this
                ->httpClient
                // The base URL can be overridden, e.g. by the test suite
                ->request('GET', (PlatformHelper::getEnv('CASTOR_RELEASES_URL') ?: self::API_URL) . $path, [
                    'timeout' => $timeout,
                ])
                ->toArray()
            ;
        } catch (ExceptionInterface) {
            $this->logger->info(\sprintf('Failed to fetch the Castor release "%s" from GitHub.', $path));

            $item->expiresAfter(60 * 10);
        }

        $this->cache->save($item->set($release));

        return $release;
    }
}
