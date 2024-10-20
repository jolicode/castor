<?php

namespace Castor\Stub;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class NodeVisitor extends NodeVisitorAbstract
{
    /** @var array<string, Node\Name> */
    private array $currentUseStatements = [];
    private bool $inInterface = false;

    public function enterNode(Node $node): ?Node
    {
        if ($node instanceof Node\Stmt\Interface_) {
            $this->inInterface = true;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $node->stmts = [];
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            if ($this->inInterface || $node->isAbstract()) {
                $node->stmts = null;
            } else {
                $node->stmts = [];
            }
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
            return NodeTraverser::REMOVE_NODE;
        }

        if ($node instanceof Node\Stmt\Namespace_) {
            if (empty($node->stmts)) {
                return NodeTraverser::REMOVE_NODE;
            }
        }

        if ($node instanceof Node\Stmt\Use_) {
            return NodeTraverser::REMOVE_NODE;
        }

        return null;
    }
}
