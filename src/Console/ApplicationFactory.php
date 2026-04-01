<?php

namespace Castor\Console;

use Castor\Helper\PathHelper;
use Castor\Kernel;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\ErrorHandler\ErrorHandler;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\VarDumper\Caster\StubCaster;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Contracts\EventDispatcher\Event;

/** @internal */
#[Exclude]
class ApplicationFactory
{
    public static function create(): SymfonyApplication
    {
        $errorHandler = self::configureDebug();

        if (class_exists(\RepackedApplication::class)) {
            $rootDir = \RepackedApplication::ROOT_DIR;
            $repacked = true;
        } else {
            $repacked = false;
        }

        $hasCastorFile = true;
        $castorFilePath = null;

        if (!$repacked) {
            // Try to see if we want to load a different castor file
            $castorFile = (new ArgvInput())->getParameterOption('--castor-file', null);

            if ($castorFile) {
                $rootDir = \dirname($castorFile);
                $castorFilePath = Path::makeRelative($castorFile, $rootDir);
            } else {
                try {
                    $rootDir = PathHelper::getRoot();
                } catch (\RuntimeException $e) {
                    $rootDir = getcwd();
                    $hasCastorFile = false;
                }
            }
        }

        $kernel = new Kernel('dev', true, $rootDir, $hasCastorFile, $castorFilePath, $repacked);
        $kernel->boot();

        $container = $kernel->getContainer();
        $container->set(ErrorHandler::class, $errorHandler);

        // @phpstan-ignore-next-line
        return $container->get(Application::class);
    }

    private static function configureDebug(): ErrorHandler
    {
        $errorHandler = ErrorHandler::register();

        AbstractCloner::$defaultCasters[Application::class] = StubCaster::cutInternals(...);
        AbstractCloner::$defaultCasters[Event::class] = StubCaster::cutInternals(...);

        return $errorHandler;
    }
}
