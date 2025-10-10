<?php

namespace Castor\Helper;

use Castor\Import\Remote\Composer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Path;

#[Exclude]
final class PathHelper
{
    public static function getCastorVendorDir(): string
    {
        return class_exists(\RepackedApplication::class) ? \RepackedApplication::ROOT_DIR . '/' . Composer::VENDOR_DIR : self::getRoot() . '/' . Composer::VENDOR_DIR;
    }

    public static function getRoot(bool $throw = true): string
    {
        static $root;

        if (null === $root) {
            if (class_exists(\RepackedApplication::class)) {
                $cwd = getcwd();
                if (false === $cwd) {
                    throw new \RuntimeException('Could not determine current working directory.');
                }

                return $root = $cwd;
            }

            $path = getcwd() ?: '/';

            while (!(file_exists($path . '/castor.php') || file_exists($path . '/.castor/castor.php'))) {
                $parent = Path::getDirectory($path);
                if ($parent === $path) {
                    if ($throw) {
                        throw new \RuntimeException('Could not find root "castor.php" file.');
                    }

                    return getcwd() ?: '/';
                }

                $path = $parent;
            }

            $root = $path;
        }

        return $root;
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
}
