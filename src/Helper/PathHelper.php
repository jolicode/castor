<?php

namespace Castor\Helper;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Path;

/** @final */
#[Exclude]
class PathHelper
{
    public static function getRoot(): string
    {
        static $root;

        if (null === $root) {
            if (class_exists(\RepackedApplication::class)) {
                return $root = Path::getDirectory(getcwd() ?: '.');
            }

            $path = getcwd() ?: '/';

            while (!(file_exists($path . '/castor.php') || file_exists($path . '/.castor/castor.php'))) {
                $parent = Path::getDirectory($path);
                if ($parent === $path) {
                    throw new \RuntimeException('Could not find root "castor.php" file.');
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
            throw new \RuntimeException(sprintf('Directory "%s" not found.', $path));
        }

        return $realpath;
    }

    public static function makeRelative(string $path): string
    {
        if (!Path::isAbsolute($path)) {
            throw new \RuntimeException(sprintf('Path "%s" is not absolute.', $path));
        }

        return Path::makeRelative($path, self::getRoot());
    }
}
