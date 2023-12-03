<?php

namespace Castor;

use JoliCode\PhpOsHelper\OsHelper;

/**
 * Platform helper inspired by Composer's Platform class.
 */
class PlatformUtil
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

    /**
     * @throws \RuntimeException If the user home could not reliably be determined
     */
    public static function getUserDirectory(): string
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
