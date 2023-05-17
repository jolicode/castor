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
}
