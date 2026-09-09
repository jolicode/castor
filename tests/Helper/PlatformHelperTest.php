<?php

namespace Castor\Tests\Helper;

use Castor\Helper\PlatformHelper;
use PHPUnit\Framework\TestCase;

class PlatformHelperTest extends TestCase
{
    private const array VARIABLES = ['XDG_CACHE_HOME', 'HOME'];

    /** @var array<string, array{server: ?string, env: ?string, process: string|false}> */
    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->originalEnvironment[$name] = [
                'server' => $_SERVER[$name] ?? null,
                'env' => $_ENV[$name] ?? null,
                'process' => getenv($name),
            ];
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnvironment as $name => $original) {
            if (null === $original['server']) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $original['server'];
            }

            if (null === $original['env']) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $original['env'];
            }

            putenv(false === $original['process'] ? $name : "{$name}={$original['process']}");
        }
    }

    public function testTheCacheDirectoryHonorsXdgCacheHome(): void
    {
        self::setEnvironmentVariable('XDG_CACHE_HOME', '/xdg/cache');

        $this->assertSame('/xdg/cache/castor', PlatformHelper::getDefaultCacheDirectory());
    }

    public function testTheCacheDirectoryDefaultsToTheHomeDirectory(): void
    {
        self::setEnvironmentVariable('XDG_CACHE_HOME', null);
        self::setEnvironmentVariable('HOME', '/home/castor');

        $this->assertSame('/home/castor/.cache/castor', PlatformHelper::getDefaultCacheDirectory());
    }

    /**
     * PlatformHelper::getEnv() reads $_SERVER, then $_ENV, then the process
     * environment: all three are set, and restored by tearDown().
     */
    private static function setEnvironmentVariable(string $name, ?string $value): void
    {
        if (null === $value) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}
