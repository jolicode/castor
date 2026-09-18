<?php

namespace Castor\Helper;

use Castor\Import\Remote\Composer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Path;

#[Exclude]
final class PathHelper
{
    private static ?string $root = null;
    private static ?string $defaultWorkingDirectory = null;

    /**
     * The root directory resolved when the application boots, which the
     * "--castor-file" option can move away from the current directory.
     */
    public static function setRoot(string $root): void
    {
        self::$root = $root;
    }

    public static function getCastorVendorDir(): string
    {
        return class_exists(\RepackedApplication::class) ? \RepackedApplication::ROOT_DIR . '/' . Composer::VENDOR_DIR : self::getRoot() . '/' . Composer::VENDOR_DIR;
    }

    public static function getRoot(bool $throw = true): string
    {
        if (null !== self::$root) {
            return self::$root;
        }

        if (class_exists(\RepackedApplication::class)) {
            $cwd = getcwd();
            if (false === $cwd) {
                throw new \RuntimeException('Could not determine current working directory.');
            }

            return self::$root = $cwd;
        }

        $root = self::findRootFromCurrentDirectory();

        if (null === $root) {
            if ($throw) {
                throw new \RuntimeException('Could not find root "castor.php" file.');
            }

            return getcwd() ?: '/';
        }

        return self::$root = $root;
    }

    /**
     * The directory the tasks run in, unless the context says otherwise. It is
     * deliberately resolved from the current directory, and never follows the
     * "--castor-file" option: pointing at another entrypoint tells Castor where
     * to read the tasks from, not where to run them.
     */
    public static function getDefaultWorkingDirectory(): string
    {
        // Memoized, as Castor moves to the working directory of the context while the
        // tasks run: resolving it again from there would return a different directory.
        if (null !== self::$defaultWorkingDirectory) {
            return self::$defaultWorkingDirectory;
        }

        if (class_exists(\RepackedApplication::class)) {
            return self::$defaultWorkingDirectory = getcwd() ?: '/';
        }

        return self::$defaultWorkingDirectory = self::findRootFromCurrentDirectory() ?? (getcwd() ?: '/');
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

    private static function findRootFromCurrentDirectory(): ?string
    {
        $path = getcwd() ?: '/';

        while (!(file_exists($path . '/castor.php') || file_exists($path . '/.castor/castor.php'))) {
            $parent = Path::getDirectory($path);
            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }

        return $path;
    }
}
