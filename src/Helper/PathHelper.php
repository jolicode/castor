<?php

namespace Castor\Helper;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Path;

#[Exclude]
final class PathHelper
{
    private static ?string $root = null;
    private static ?string $castorVendorDir = null;
    private static ?string $defaultWorkingDirectory = null;

    /**
     * All the directories are resolved once, when the application boots, as the
     * process then moves to the working directory of the context while the tasks
     * run: resolving them again from there would return different directories.
     *
     * @internal
     */
    public static function initialize(string $root, string $castorVendorDir, string $defaultWorkingDirectory): void
    {
        self::$root = $root;
        self::$castorVendorDir = $castorVendorDir;
        self::$defaultWorkingDirectory = $defaultWorkingDirectory;
    }

    public static function getCastorVendorDir(): string
    {
        return self::$castorVendorDir ?? throw self::notInitialized();
    }

    /**
     * @param bool $throw No longer used: the root directory is always resolved when the application boots
     */
    public static function getRoot(bool $throw = true): string
    {
        return self::$root ?? throw self::notInitialized();
    }

    /**
     * The directory the tasks run in, unless the context says otherwise. It is
     * resolved from the directory castor was started in, and never follows the
     * "--castor-file" option: pointing at another entrypoint tells Castor where
     * to read the tasks from, not where to run them.
     */
    public static function getDefaultWorkingDirectory(): string
    {
        return self::$defaultWorkingDirectory ?? throw self::notInitialized();
    }

    public static function realpath(string $path): string
    {
        $realpath = realpath($path);

        if (false === $realpath) {
            throw new \RuntimeException(\sprintf('Directory "%s" not found.', $path));
        }

        return $realpath;
    }

    public static function makeRelative(string $path): string
    {
        if (!Path::isAbsolute($path)) {
            throw new \RuntimeException(\sprintf('Path "%s" is not absolute.', $path));
        }

        return Path::makeRelative($path, self::getRoot());
    }

    private static function notInitialized(): \LogicException
    {
        return new \LogicException(\sprintf('The "%s" class has not been initialized yet.', self::class));
    }
}
