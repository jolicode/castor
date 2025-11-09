<?php

namespace Castor\Helper;

use JoliCode\PhpOsHelper\OsHelper;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Platform helper inspired by Composer's Platform class.
 *
 * @internal
 */
#[Exclude]
final class PlatformHelper
{
    /**
     * getenv() equivalent but reads from the runtime global variables first.
     */
    public static function getEnv(string $name): string|false
    {
        if (\array_key_exists($name, $_SERVER)) {
            return (string) $_SERVER[$name];
        }
        if (\array_key_exists($name, $_ENV)) {
            return (string) $_ENV[$name];
        }

        return getenv($name);
    }

    public static function getDefaultCacheDirectory(): string
    {
        try {
            $home = self::getUserDirectory();
            $directory = $home ? $home . '/.cache' : sys_get_temp_dir();
        } catch (\RuntimeException) {
            $directory = sys_get_temp_dir();
        }

        return $directory . '/castor';
    }

    /**
     * @throws \RuntimeException If the user home could not reliably be determined
     */
    private static function getUserDirectory(): string
    {
        if (false !== ($home = self::getEnv('HOME'))) {
            return $home;
        }

        if (OsHelper::isWindows() && false !== ($home = self::getEnv('USERPROFILE'))) {
            return $home;
        }

        if (\function_exists('posix_getuid') && \function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_getuid());

            if ($info) {
                return $info['dir'];
            }
        }

        throw new \RuntimeException('Could not determine user directory.');
    }
}
