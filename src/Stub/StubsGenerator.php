<?php

namespace Castor\Stub;

use Castor\Console\Application;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\Finder\Finder;

use function Castor\log;

/** @internal */
final class StubsGenerator
{
    public function generateStubsIfNeeded(string $dest): void
    {
        if ($this->shouldGenerate($dest)) {
            log('Generating stubs...', 'debug');
            $this->generateStubs($dest);
        }
    }

    public function generateStubs(string $dest): void
    {
        $basePath = \dirname(__DIR__, 2);
        $finder = new Finder();

        $finder
            ->files()
            ->in("{$basePath}/src")
            ->name('*.php')
            ->sortByName()
        ;

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $stmts = [];

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor());

        foreach ($finder as $file) {
            $fileStmts = $parser->parse((string) file_get_contents($file->getPathname()));
            // @phpstan-ignore-next-line
            $stmts = array_merge($stmts, $traverser->traverse($fileStmts));
        }

        // Add some very frequently used classes
        $frequentlyUsedClasses = [
            \Symfony\Component\Console\Application::class,
            \Symfony\Component\Console\Input\InputArgument::class,
            \Symfony\Component\Console\Input\InputInterface::class,
            \Symfony\Component\Console\Input\InputOption::class,
            \Symfony\Component\Console\Output\OutputInterface::class,
            \Symfony\Component\Console\Style\SymfonyStyle::class,
            \Symfony\Component\Filesystem\Filesystem::class,
            \Symfony\Component\Filesystem\Path::class,
            \Symfony\Component\Finder\Finder::class,
            \Symfony\Component\Process\Process::class,
        ];

        foreach ($frequentlyUsedClasses as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            if (!$file) {
                continue;
            }
            $fileStmts = $parser->parse((string) file_get_contents($file));
            // @phpstan-ignore-next-line
            $stmts = array_merge($stmts, $traverser->traverse($fileStmts));
        }

        array_unshift($stmts, new \PhpParser\Node\Stmt\Nop([
            'comments' => [
                new \PhpParser\Comment\Doc(sprintf('// castor version: %s', Application::VERSION)),
            ],
        ]));

        $code = (new Standard())->prettyPrintFile($stmts);

        file_put_contents($dest, $code);
    }

    private function shouldGenerate(string $dest): bool
    {
        if (!file_exists($dest)) {
            return true;
        }

        $content = (string) file_get_contents($dest);
        preg_match('{^// castor version: (.+)$}m', $content, $matches);
        if (!$matches) {
            return true;
        }

        return Application::VERSION !== $matches[1];
    }
}
