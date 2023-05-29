<?php

namespace Castor\Console;

use Castor\Console\Command\CastorFileNotFoundCommand;
use Castor\GlobalHelper;
use Castor\PathHelper;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\SingleCommandApplication;

/** @internal */
class ApplicationFactory
{
    public static function run(): void
    {
        $input = new ArgvInput();
        $output = new ConsoleOutput();

        $logger = new Logger('castor', [
            new ConsoleHandler($output),
        ]);

        $application = self::create($logger);
        $application->run($input, $output);
    }

    public static function create(LoggerInterface $logger): Application|SingleCommandApplication
    {
        try {
            $rootDir = PathHelper::getRoot();
        } catch (\RuntimeException $e) {
            return new CastorFileNotFoundCommand($e);
        }

        GlobalHelper::setLogger($logger);

        return new Application($rootDir);
    }
}
