<?php

namespace Castor\Console;

use Castor\PathHelper;
use Symfony\Component\Console\Application as SymfonyApplication;

/** @internal */
class ApplicationFactory
{
    public static function create(): SymfonyApplication
    {
        try {
            $rootDir = PathHelper::getRoot();
        } catch (\RuntimeException $e) {
            return new CastorFileNotFoundApplication($e);
        }

        if (class_exists(\RepackedApplication::class)) {
            // @phpstan-ignore-next-line
            return new \RepackedApplication($rootDir);
        }

        return new Application($rootDir);
    }
}
