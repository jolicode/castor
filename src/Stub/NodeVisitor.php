<?php

namespace Castor\Stub;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use PHPStan\PhpDocParser\Ast\NodeTraverser as PhpDocNodeTraverser;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Printer\Printer;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class NodeVisitor extends NodeVisitorAbstract
{
    private const INTERNAL_CLASSES_FORCED = [
        \Castor\Console\Application::class,
        \Castor\Console\Command\TaskCommand::class,
        \Castor\Console\Input\GetRawTokenTrait::class,
    ];

    private bool $inInterface = false;

    public function __construct(
        private readonly PhpDocNodeTraverser $phpDocNodeTraverser,
        private readonly Lexer $lexer,
        private readonly PhpDocParser $phpDocParser,
    ) {
    }

    /**
     * @return int|Node|null
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            // Remove comments at namespace level
            $node->setAttribute('comments', null);
        }

        if ($node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            $docComment = $node->getDocComment();

            // Remove internal classes and functions
            if (null !== $docComment && str_contains($docComment->getText(), '@internal')) {
                if ($node instanceof Node\Stmt\ClassMethod || !$node->namespacedName || !\in_array($node->namespacedName->toString(), self::INTERNAL_CLASSES_FORCED, true)) {
                    return self::REMOVE_NODE;
                }
            }

            $this->replaceRelativeClassNameByFqcInPhpdoc($node);
        }

        if ($node instanceof Node\Stmt\Interface_) {
            $this->inInterface = true;
        }

        // Empty functions body
        if ($node instanceof Node\Stmt\Function_) {
            $node->stmts = [];
        }

        // Empty class/interface methods body
        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($this->inInterface || $node->isAbstract()) {
                $node->stmts = null;
            } else {
                $node->stmts = [];
            }
        }

        return null;
    }

    /**
     * @return int|Node|Node[]|null
     */
    public function leaveNode(Node $node, bool $preserveStack = false)
    {
        // Remove "use" statements
        if ($node instanceof Node\Stmt\Use_) {
            return self::REMOVE_NODE;
        }

        if ($node instanceof Node\Stmt\Interface_) {
            $this->inInterface = false;
        }

        // Remove private class members
        if (($node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Stmt\ClassConst
            || $node instanceof Node\Stmt\Property) && $node->isPrivate()
        ) {
            return self::REMOVE_NODE;
        }

        return null;
    }

    private function replaceRelativeClassNameByFqcInPhpdoc(Node\Stmt\ClassLike|Node\Stmt\Function_|Node\Stmt\ClassMethod $node): void
    {
        $docComment = $node->getDocComment();

        if (null === $docComment) {
            return;
        }
        $tokens = new TokenIterator($this->lexer->tokenize($docComment->getText()));
        $phpDocNode = $this->phpDocParser->parse($tokens);

        /** @var PhpDocNode $newPhpDocNode */
        [$newPhpDocNode] = $this->phpDocNodeTraverser->traverse([$phpDocNode]);

        $printer = new Printer();
        $newPhpDocString = $printer->printFormatPreserving($newPhpDocNode, $phpDocNode, $tokens);

        $node->setDocComment(new Doc($newPhpDocString));
    }
}
