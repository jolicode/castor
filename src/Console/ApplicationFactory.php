<?php

namespace Castor\Console;

use Castor\Console\Command\CastorFileNotFoundCommand;
use Castor\ContextRegistry;
use Castor\PathHelper;
use Castor\Stub\StubsGenerator;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\SingleCommandApplication;

/** @internal */
class ApplicationFactory
{
    public static function run(): void
    {
        $application = self::create();

        $input = new ArgvInput();
        $output = new ConsoleOutput();

        $logger = new Logger('castor', [
            new ConsoleHandler($output),
        ]);

        ContextRegistry::setLogger($logger);

        $application->run($input, $output);
    }

    private static function create(): Application|SingleCommandApplication
    {
        try {
            $rootDir = PathHelper::getRoot();

            $stubSourcePath = $rootDir . '/.castor.stub.php';
            if (!file_exists($stubSourcePath)) {
                (new StubsGenerator())->generateStubs($stubSourcePath);
            }
        } catch (\RuntimeException $e) {
            return new CastorFileNotFoundCommand($e);
        }

        return new Application($rootDir);
    }
}
