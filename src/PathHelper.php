<?php

namespace Castor;

class PathHelper
{
    public static function getCwd(): string
    {
        $cwd = getcwd();

        if (false === $cwd) {
            throw new \RuntimeException('Unable to get current working directory.');
        }

        return $cwd;
    }

    public static function realpath(string $path): string
    {
        $realpath = realpath($path);

        if (false === $realpath) {
            throw new \RuntimeException(sprintf('Directory "%s" not found.', $path));
        }

        return $realpath;
    }
}
