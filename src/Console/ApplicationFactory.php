<?php

namespace Castor\Console;

use Castor\Console\Command\CastorFileNotFoundCommand;
use Castor\PathHelper;
use Symfony\Component\Console\SingleCommandApplication;

/** @internal */
class ApplicationFactory
{
    public static function create(): Application|SingleCommandApplication
    {
        try {
            $rootDir = PathHelper::getRoot();
        } catch (\RuntimeException $e) {
            return new CastorFileNotFoundCommand($e);
        }

        return new Application($rootDir);
    }
}
