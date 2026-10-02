<?php

namespace Castor\Console;

use Castor\Helper\PathHelper;
use Castor\Import\Remote\Composer;
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

        // There is no current directory at all when the one the shell sits in has been deleted.
        $currentDir = getcwd() ?: '/';
        $repacked = class_exists(\RepackedApplication::class);
        $hasCastorFile = true;
        $castorFilePath = null;

        if ($repacked) {
            $rootDir = \RepackedApplication::ROOT_DIR;
            // The root of a repacked application lives inside its phar, so only the remote packages
            // are read from there: everything else is resolved from the directory it runs in.
            PathHelper::initialize($currentDir, $rootDir . '/' . Composer::VENDOR_DIR, $currentDir);
        } else {
            // Try to see if we want to load a different castor file
            $castorFile = new ArgvInput()->getParameterOption('--castor-file', null);
            $detectedRootDir = self::findRootDir($currentDir);

            if ($castorFile) {
                $rootDir = \dirname($castorFile);
                // A castor file living in a ".castor" directory belongs to the project holding
                // it, just like the auto-detection considers "<project>/.castor/castor.php".
                // Without this, the remote packages would be installed in ".castor/.castor".
                if ('.castor' === basename($rootDir)) {
                    $rootDir = \dirname($rootDir);
                }
                $castorFilePath = Path::makeRelative($castorFile, $rootDir);
            } else {
                $rootDir = $detectedRootDir ?? $currentDir;
                $hasCastorFile = null !== $detectedRootDir;
            }

            // Once the process moves to the working directory of the context, a relative root would
            // resolve against the wrong directory.
            $rootDir = Path::makeAbsolute($rootDir, $currentDir);

            // Everything resolved against the root, like the remote packages, follows the
            // "--castor-file" option instead of looking for a castor file from the directory castor
            // was started in. The working directory of the context does not: the tasks still run
            // where castor was started from.
            PathHelper::initialize($rootDir, $rootDir . '/' . Composer::VENDOR_DIR, $detectedRootDir ?? $currentDir);
        }

        $kernel = new Kernel('dev', true, $rootDir, $hasCastorFile, $castorFilePath, $repacked);
        $kernel->boot();

        $container = $kernel->getContainer();
        $container->set(ErrorHandler::class, $errorHandler);

        // @phpstan-ignore-next-line
        return $container->get(Application::class);
    }

    private static function findRootDir(string $path): ?string
    {
        while (!(file_exists($path . '/castor.php') || file_exists($path . '/.castor/castor.php'))) {
            $parent = Path::getDirectory($path);
            if ($parent === $path) {
                return null;
            }

            $path = $parent;
        }

        return $path;
    }

    private static function configureDebug(): ErrorHandler
    {
        $errorHandler = ErrorHandler::register();

        AbstractCloner::$defaultCasters[Application::class] = StubCaster::cutInternals(...);
        AbstractCloner::$defaultCasters[Event::class] = StubCaster::cutInternals(...);

        return $errorHandler;
    }
}
