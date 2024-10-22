<?php

namespace Castor\Tests\Stub;

use Castor\Stub\NodeVisitor;
use Castor\Stub\PhpDocNodeVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\PhpDocParser\Ast\NodeTraverser as PhpDocNodeTraverser;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class NodeVisitorTest extends TestCase
{
    /**
     * @dataProvider provideCode
     */
    public function test(string $fixture, string $fixtureCode, string $expectedCode)
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $nameResolver = new NameResolver();

        $lexer = new Lexer();
        $constExprParser = new ConstExprParser();
        $typeParser = new TypeParser($constExprParser);
        $phpDocParser = new PhpDocParser($typeParser, $constExprParser, usedAttributes: [
            'lines' => true,
            'indexes' => true,
        ]);

        $phpDocNodeTraverser = new PhpDocNodeTraverser([
            new PhpDocNodeVisitor($nameResolver),
        ]);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($nameResolver);
        $traverser->addVisitor(new NodeVisitor($phpDocNodeTraverser, $lexer, $phpDocParser));

        $fileStmts = $parser->parse($fixtureCode);
        $fileStmts = $traverser->traverse($fileStmts);

        $generatedCode = (new Standard())->prettyPrintFile($fileStmts);

        $this->assertSame($expectedCode, $generatedCode, "Fail to assert the generated code is identical for fixture \"{$fixture}\"");
    }

    public function provideCode(): \Generator
    {
        $dirs = (new Finder())
            ->in(__DIR__ . '/fixtures')
            ->directories()
        ;

        foreach ($dirs as $dir) {
            $path = $dir->getRealPath();
            $expected = $path . '/expected.php';
            $input = $path . '/input.php';

            yield [
                basename($path),
                trim(file_get_contents($input)),
                trim(file_get_contents($expected)),
            ];
        }
    }
}
