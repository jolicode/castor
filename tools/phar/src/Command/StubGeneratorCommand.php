<?php

namespace Castor\Tools\Command;

use Castor\Tools\NodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Finder\Finder;

class StubGeneratorCommand extends SingleCommandApplication
{
    protected function configure()
    {
        $this->setName('stub-generator');
        $this->addArgument('path', InputArgument::OPTIONAL, 'Path to the project generating the stub', __DIR__ . '/../../../..');
        $this->addArgument('dest', InputArgument::OPTIONAL, 'Destination file of the stub', __DIR__ . '/../../../../.castor.stub.php');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $input->getArgument('path');
        $finder = new Finder();

        $finder
            ->files()
            ->in("{$path}/src")
            ->append([
                // Add some very frequently used classes
                "{$path}/vendor/symfony/console/Application.php",
                "{$path}/vendor/symfony/console/Input/InputOption.php",
                "{$path}/vendor/symfony/console/Input/InputArgument.php",
                "{$path}/vendor/symfony/console/Input/InputInterface.php",
                "{$path}/vendor/symfony/console/Output/OutputInterface.php",
                "{$path}/vendor/symfony/console/Style/SymfonyStyle.php",
                "{$path}/vendor/symfony/process/Process.php",
                "{$path}/vendor/symfony/finder/Finder.php",
            ])
            ->name('*.php')
            ->sortByName()
        ;

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $stmts = [];

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor());

        foreach ($finder as $file) {
            $fileStmts = $parser->parse(file_get_contents($file->getPathname()));
            $stmts = array_merge($stmts, $traverser->traverse($fileStmts));
        }

        $prettyPrinter = new Standard();
        $code = $prettyPrinter->prettyPrintFile($stmts);

        $dest = $input->getArgument('dest');
        file_put_contents($dest, $code);

        return 0;
    }
}
