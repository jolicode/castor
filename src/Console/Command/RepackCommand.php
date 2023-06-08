<?php

namespace Castor\Console\Command;

use Castor\Console\Application;
use Castor\FunctionFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class RepackCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('repack')
            ->addOption('output', null, InputOption::VALUE_REQUIRED, 'The path of the phar file', 'my-app.phar')
            ->addOption('app-name', null, InputOption::VALUE_REQUIRED, 'The name of the phar application', Application::NAME)
            ->addOption('app-version', null, InputOption::VALUE_REQUIRED, 'The version of the phar application', Application::VERSION)
            ->setHidden(true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pharOutput = $input->getOption('output');

        if (str_starts_with(__FILE__, 'phar:')) {
            copy(\Phar::running(false), $pharOutput);
        } elseif (file_exists($f = __DIR__ . '/../../../tools/phar/build/castor.linux-amd64.phar')) {
            copy($f, $pharOutput);
        } else {
            throw new \RuntimeException('You must run this command from a phar or from a dev environment with the phar built.');
        }

        $phar = new \Phar($pharOutput);
        foreach (FunctionFinder::$files as $file) {
            $phar->addFile($file, $file);
        }

        $filesAsStrings = var_export(FunctionFinder::$files, true);
        $appName = $input->getOption('app-name');
        $appVersion = $input->getOption('app-version');
        $entryPoint = str_replace(
            $tail = 'ApplicationFactory::create()->run();',
            <<<PHP
            class RepackedApplication extends Castor\\Console\\Application
            {
                const NAME = '{$appName}';
                const VERSION = '{$appVersion}';

                public static array \$files = {$filesAsStrings};
            }

            {$tail};
            PHP,
            $phar['bin/castor']->getContent()
        );
        $phar->addFromString('bin/castor', $entryPoint);

        chmod($pharOutput, 0o755);

        return Command::SUCCESS;
    }
}
