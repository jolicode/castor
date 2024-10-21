<?php

namespace Castor\Stub;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeAbstract;
use PhpParser\NodeTraverser;
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
    ];

    private bool $inInterface = false;

    public function __construct(
        private readonly PhpDocNodeTraverser $phpDocNodeTraverser,
        private readonly Lexer $lexer,
        private readonly PhpDocParser $phpDocParser,
    ) {
    }

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Interface_) {
            $this->inInterface = true;

            $this->replaceRelativeClassNameByFqcInPhpdoc($node);
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->replaceRelativeClassNameByFqcInPhpdoc($node);
        }

        if ($node instanceof Node\Stmt\Function_) {
            $node->stmts = [];

            $this->replaceRelativeClassNameByFqcInPhpdoc($node);
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($this->inInterface || $node->isAbstract()) {
                $node->stmts = null;
            } else {
                $node->stmts = [];
            }

            $this->replaceRelativeClassNameByFqcInPhpdoc($node);
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            $node->setAttribute('comments', null);
        }

        return null;
    }

    public function leaveNode(Node $node, bool $preserveStack = false): ?int
    {
        if ($node instanceof Node\Stmt\Interface_) {
            $this->inInterface = false;
        }

        $docComment = $node->getDocComment();

        if (null !== $docComment && str_contains($docComment->getText(), '@internal')) {
            if (!$node instanceof Node\Stmt\Class_ || !$node->namespacedName || !\in_array($node->namespacedName->toString(), self::INTERNAL_CLASSES_FORCED, true)) {
                return NodeTraverser::REMOVE_NODE;
            }
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            if (empty($node->stmts)) {
                return NodeTraverser::REMOVE_NODE;
            }
        }

        if ($node instanceof Node\Stmt\Use_) {
            return NodeTraverser::REMOVE_NODE;
        }

        if (($node instanceof Node\Stmt\ClassMethod
            || $node instanceof Node\Stmt\ClassConst
            || $node instanceof Node\Stmt\Property) && $node->isPrivate()
        ) {
            return NodeTraverser::REMOVE_NODE;
        }

        return null;
    }

    private function replaceRelativeClassNameByFqcInPhpdoc(NodeAbstract $node): void
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
