<?php

namespace Castor\Helper;

use JoliCode\PhpOsHelper\OsHelper;
use Laravel\AgentDetector\AgentDetector;
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

    public static function isRunningInAgentContext(): bool
    {
        if (self::getEnv('CASTOR_DISABLE_AGENT_DETECTION')) {
            return false;
        }

        static $result = null;

        return $result ??= AgentDetector::detect()->isAgent;
    }

    /**
     * $XDG_CACHE_HOME/castor, or ~/.cache/castor. When the home directory
     * cannot be determined, a per-user directory in the system temporary
     * directory is used: the cache holds data written by the tasks, it must
     * not be shared with the other users of the machine.
     */
    public static function getDefaultCacheDirectory(): string
    {
        if ($xdgCacheHome = self::getEnv('XDG_CACHE_HOME')) {
            return $xdgCacheHome . '/castor';
        }

        try {
            $home = self::getUserDirectory();
        } catch (\RuntimeException) {
            $home = '';
        }

        if ($home) {
            return $home . '/.cache/castor';
        }

        $uid = \function_exists('posix_getuid') ? posix_getuid() : get_current_user();

        return sys_get_temp_dir() . '/castor-' . $uid;
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
