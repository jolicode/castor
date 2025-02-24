<?php

namespace Castor\Stub;

use Castor\Console\Application;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\PhpDocParser\Ast\NodeTraverser as PhpDocNodeTraverser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Finder\Finder;

/** @internal */
final readonly class StubsGenerator
{
    public function __construct(
        private string $rootDir,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function generateStubsIfNeeded(): void
    {
        if (null !== $dest = $this->shouldGenerate()) {
            $this->logger->debug('Generating stubs...');
            $this->generateCastorStubs($dest);
        }
    }

    private function generateCastorStubs(string $dest): void
    {
        if (!is_writable(\dirname($dest))) {
            $this->logger->warning("Could not generate stubs as the destination \"{$dest}\" is not writeable.");

            return;
        }

        $basePath = \dirname(__DIR__, 2);
        $finder = new Finder();

        $finder
            ->files()
            ->in("{$basePath}/src")
            ->name('*.php')
            ->sortByName()
        ;

        $files = iterator_to_array($finder);

        // Add some very frequently used classes
        $frequentlyUsedClasses = [
            \Psr\Cache\CacheItemInterface::class,
            \Psr\Cache\CacheItemPoolInterface::class,
            \Psr\Cache\InvalidArgumentException::class,
            \Psr\Cache\CacheException::class,
            LoggerInterface::class,
            \Symfony\Component\Console\Application::class,
            \Symfony\Component\Console\Command\Command::class,
            \Symfony\Component\Console\Completion\CompletionInput::class,
            \Symfony\Component\Console\Helper\ProgressBar::class,
            \Symfony\Component\Console\Helper\TableSeparator::class,
            \Symfony\Component\Console\Helper\TableStyle::class,
            \Symfony\Component\Console\Input\InputArgument::class,
            \Symfony\Component\Console\Input\InputInterface::class,
            \Symfony\Component\Console\Input\InputOption::class,
            \Symfony\Component\Console\Output\OutputInterface::class,
            \Symfony\Component\Console\Question\Question::class,
            \Symfony\Component\Console\Style\SymfonyStyle::class,
            \Symfony\Contracts\EventDispatcher\Event::class,
            \Symfony\Component\EventDispatcher\EventDispatcherInterface::class,
            \Symfony\Component\EventDispatcher\EventSubscriberInterface::class,
            \Symfony\Component\Filesystem\Exception\ExceptionInterface::class,
            \Symfony\Component\Filesystem\Exception\FileNotFoundException::class,
            \Symfony\Component\Filesystem\Exception\InvalidArgumentException::class,
            \Symfony\Component\Filesystem\Exception\IOException::class,
            \Symfony\Component\Filesystem\Exception\RuntimeException::class,
            \Symfony\Component\Filesystem\Filesystem::class,
            \Symfony\Component\Filesystem\Path::class,
            \Symfony\Component\Finder\Exception\DirectoryNotFoundException::class,
            Finder::class,
            \Symfony\Component\Finder\SplFileInfo::class,
            \Symfony\Component\Process\Exception\ExceptionInterface::class,
            \Symfony\Component\Process\Exception\LogicException::class,
            \Symfony\Component\Process\Exception\ProcessFailedException::class,
            \Symfony\Component\Process\Exception\ProcessSignaledException::class,
            \Symfony\Component\Process\Exception\ProcessTimedOutException::class,
            \Symfony\Component\Process\Exception\RuntimeException::class,
            \Symfony\Component\Process\ExecutableFinder::class,
            \Symfony\Component\Process\Process::class,
            \Symfony\Component\String\AbstractString::class,
            \Symfony\Component\String\AbstractUnicodeString::class,
            \Symfony\Component\String\ByteString::class,
            \Symfony\Component\String\CodePointString::class,
            \Symfony\Component\String\Exception\ExceptionInterface::class,
            \Symfony\Component\String\UnicodeString::class,
            \Symfony\Contracts\Cache\CacheInterface::class,
            \Symfony\Contracts\Cache\CallbackInterface::class,
            \Symfony\Contracts\Cache\ItemInterface::class,
            \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface::class,
            \Symfony\Contracts\HttpClient\Exception\ExceptionInterface::class,
            \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface::class,
            \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface::class,
            \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface::class,
            \Symfony\Contracts\HttpClient\HttpClientInterface::class,
            \Symfony\Contracts\HttpClient\ResponseInterface::class,
            \Symfony\Contracts\HttpClient\ResponseStreamInterface::class,
        ];

        foreach ($frequentlyUsedClasses as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            if (!$file) {
                continue;
            }

            $files[] = $file;
        }

        // Expose some functions provided by vendors
        $files[] = "{$basePath}/vendor/symfony/string/Resources/functions.php";
        $files[] = "{$basePath}/vendor/symfony/var-dumper/Resources/functions/dump.php";

        $stmts = $this->doGenerate($files);

        array_unshift($stmts, new Stmt\Nop([
            'comments' => [
                new \PhpParser\Comment\Doc(\sprintf('// castor version: %s', Application::VERSION)),
                new \PhpParser\Comment\Doc('// This file has been generated by castor. Do not edit it manually.'),
                new \PhpParser\Comment\Doc('// It helps IDEs to provide better autocompletion and analysis.'),
                new \PhpParser\Comment\Doc('// You can safely ignore this file in your VCS.'),
                new \PhpParser\Comment\Doc('// ".castor.stub.php" by default is in the same location of "castor.php".'),
                new \PhpParser\Comment\Doc('// You can also move this file at the root of your project or to ".castor/.castor.stub.php".'),
                new \PhpParser\Comment\Doc(''),
            ],
        ]));

        $code = (new Standard())->prettyPrintFile($stmts) . \PHP_EOL;

        file_put_contents($dest, $code);
    }

    /**
     * @param string[]|\SplFileInfo[] $files
     *
     * @return Node[]
     */
    private function doGenerate(array $files): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $stmts = [];

        $nameResolver = new NameResolver();

        $parserConfig = new ParserConfig([
            'lines' => true,
            'indexes' => true,
        ]);
        $lexer = new Lexer($parserConfig);
        $constExprParser = new ConstExprParser($parserConfig);
        $typeParser = new TypeParser($parserConfig, $constExprParser);
        $phpDocParser = new PhpDocParser($parserConfig, $typeParser, $constExprParser);

        $phpDocNodeTraverser = new PhpDocNodeTraverser([new PhpDocNodeVisitor($nameResolver)]);

        $nodeVisitor = new NodeVisitor($phpDocNodeTraverser, $lexer, $phpDocParser);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor($nodeVisitor);

        // Parse all files one by one, traverse the related AST then merge all statements
        foreach ($files as $file) {
            $fileStmts = $parser->parse((string) file_get_contents((string) $file));
            if (!$fileStmts) {
                continue;
            }

            $firstStmt = $fileStmts[0];
            if (!$firstStmt instanceof Stmt\Namespace_) {
                $fileStmts = [new Stmt\Namespace_(null, $fileStmts)];
            }

            $stmts = array_merge($stmts, $traverser->traverse($fileStmts));
        }

        // Traverse the AST containing all statements in once to clean more stuff
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CleanVisitor());

        return $traverser->traverse($stmts);
    }

    private function shouldGenerate(): ?string
    {
        // Do not generate stubs when working on castor
        if (($cwd = getcwd()) && str_starts_with(\dirname(__DIR__, 2), $cwd)) {
            return null;
        }

        $files = [
            $this->rootDir . '/.castor.stub.php',
            $this->rootDir . '/.castor/.castor.stub.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                return $this->isOutdated($file) ? $file : null;
            }
        }

        $generateLocation = $files[0];
        if (file_exists($this->rootDir . '/.castor/castor.php')) {
            $generateLocation = $files[1];
        }

        return $generateLocation;
    }

    private function isOutdated(string $file): bool
    {
        $content = (string) file_get_contents($file);
        preg_match('{^// castor version: (.+)$}m', $content, $matches);
        if (!$matches) {
            return true;
        }

        return Application::VERSION !== $matches[1];
    }
}
