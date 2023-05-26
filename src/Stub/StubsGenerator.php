<?php

namespace Castor\Stub;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Finder\Finder;

/** @internal */
final class StubsGenerator
{
    public function generateStubs(string $dest, string $basePath = __DIR__ . '/../..'): void
    {
        $finder = new Finder();

        $finder
            ->files()
            ->in("{$basePath}/src")
            ->append([
                // Add some very frequently used classes
                "{$basePath}/vendor/symfony/console/Application.php",
                "{$basePath}/vendor/symfony/console/Input/InputOption.php",
                "{$basePath}/vendor/symfony/console/Input/InputArgument.php",
                "{$basePath}/vendor/symfony/console/Input/InputInterface.php",
                "{$basePath}/vendor/symfony/console/Output/OutputInterface.php",
                "{$basePath}/vendor/symfony/console/Style/SymfonyStyle.php",
                "{$basePath}/vendor/symfony/process/Process.php",
                "{$basePath}/vendor/symfony/finder/Finder.php",
                "{$basePath}/vendor/symfony/filesystem/Filesystem.php",
                "{$basePath}/vendor/symfony/filesystem/Path.php",
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

        file_put_contents($dest, $code);
    }
}
