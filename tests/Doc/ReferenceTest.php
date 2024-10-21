<?php

namespace Castor\Tests\Doc;

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

class ReferenceTest extends TestCase
{
    public function testFunctionsAreReferenced(): void
    {
        $functions = $this->getBuiltInFunctions();

        if (!$functions) {
            $this->fail('Could not find any built-in functions');
        }

        $lists = $this->getReferencedLists();

        $this->assertNotEmpty($lists);
        $this->assertArrayHasKey('Functions', $lists);
        $this->assertIsArray($lists['Functions']);
        $this->assertNotEmpty($lists['Functions']);

        $referencedFunctions = $lists['Functions'];

        // Ensure all functions are either referenced, internal or deprecated
        foreach ($functions as $function) {
            $reflection = new \ReflectionFunction("\\Castor\\{$function}");
            $comment = $reflection->getDocComment();

            if (str_contains($comment, '@internal')) {
                $this->fail("Built-in function \"{$function}\" is marked as internal, it should moved to src/functions-internal.php");
            }

            if (str_contains($comment, '@deprecated')) {
                $this->assertNotContains($function, $lists['Functions'], "Built-in function \"{$function}\" is deprecated and SHOULD NOT be listed in the documentation doc/reference.md");
            } else {
                $this->assertContains($function, $lists['Functions'], "Built-in function \"{$function}\" should be listed in the documentation doc/reference.md");
            }

            unset($referencedFunctions[array_search($function, $referencedFunctions)]);
        }

        $this->assertEmpty($referencedFunctions, 'Some functions are listed in the documentation doc/reference.md but do not exist: ' . implode(', ', $referencedFunctions));

        $sortedFunctions = $lists['Functions'];
        sort($sortedFunctions);

        $this->assertSame($sortedFunctions, $lists['Functions'], 'Functions should be listed in alphabetical order in the documentation doc/reference.md');
    }

    public function testAttributesAreReferenced(): void
    {
        $attributeClasses = $this->getAttributeClasses();

        if (!$attributeClasses) {
            $this->fail('Could not find any Attribute classes');
        }

        $lists = $this->getReferencedLists();

        $this->assertNotEmpty($lists);
        $this->assertArrayHasKey('Attributes', $lists);
        $this->assertIsArray($lists['Attributes']);
        $this->assertNotEmpty($lists['Attributes']);

        $referencedAttributes = $lists['Attributes'];

        // Ensure all attributes are referenced
        foreach ($attributeClasses as $reflection) {
            $shortName = $reflection->getShortName();

            $this->assertContains($shortName, $lists['Attributes'], "Attribute \"{$shortName}\" should be listed in the documentation doc/reference.md");

            unset($referencedAttributes[array_search($shortName, $referencedAttributes)]);
        }

        // Ensure all referenced attributes exist
        $this->assertEmpty($referencedAttributes, 'Some attributes are listed in the documentation doc/reference.md but do not exist: ' . implode(', ', $referencedAttributes));

        $sortedFunctions = $lists['Attributes'];
        sort($sortedFunctions);

        $this->assertSame($sortedFunctions, $lists['Attributes'], 'Attributes should be listed in alphabetical order in the documentation doc/reference.md');
    }

    /**
     * @return list<string>
     */
    private function getBuiltInFunctions(): array
    {
        $file = __DIR__ . '/../../src/functions.php';

        $parser = (new ParserFactory())->createForHostVersion();

        $visitor = new class extends NodeVisitorAbstract {
            public $functions = [];

            public function enterNode(Node $node): int|Node|null
            {
                if ($node instanceof Function_) {
                    $this->functions[] = $node->name->name;
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);

        $fileStmts = $parser->parse((string) file_get_contents($file));

        if (!$fileStmts) {
            return [];
        }

        $traverser->traverse($fileStmts);

        return $visitor->functions;
    }

    /**
     * Naive attributes classes finder.
     *
     * @return list<\ReflectionClass>
     */
    private function getAttributeClasses(): array
    {
        $attributeClasses = [];

        $finder = Finder::create()->files()->in(__DIR__ . '/../../src/Attribute')->name('*.php');

        foreach ($finder as $file) {
            require_once $file->getPathname();

            $shortName = $file->getBasename('.php');
            $reflection = new \ReflectionClass("Castor\\Attribute\\{$shortName}");

            if (!$reflection->getAttributes()) {
                continue;
            }

            foreach ($reflection->getAttributes() as $attribute) {
                if ('Attribute' === $attribute->getName()) {
                    $attributeClasses[] = $reflection;

                    break;
                }
            }
        }

        return $attributeClasses;
    }

    /**
     * Naive Markdown list extractor, grouped by chapters.
     */
    private function getReferencedLists(): array
    {
        $file = __DIR__ . '/../../doc/reference.md';
        $doc = file_get_contents($file);

        $lines = explode("\n", $doc);

        $chapters = [];
        $currentChapter = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^#+ (.*)/', $line, $matches)) {
                $currentChapter = $matches[1];
                $chapters[$currentChapter] = [];
            } elseif (preg_match('/^\-+ (.*)/', $line, $matches)) {
                $item = $matches[1];
                if (preg_match('/\[`(.*)`\]\((.*)\)/', $item, $anchorMatches)) {
                    $item = $anchorMatches[1];
                }
                $chapters[$currentChapter][] = $item;
            }
        }

        return $chapters;
    }
}
