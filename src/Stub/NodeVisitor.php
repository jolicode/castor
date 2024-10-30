<?php

namespace Castor\Stub;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
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

    /** @var array<string, string[]> */
    private array $usesByNamespace = [];

    private ?string $currentNamespace = null;
    private bool $inInterface = false;

    /**
     * @return null|int|Node
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name ? $node->name->toString() : null;

            // Remove comments at namespace level
            $node->setAttribute('comments', null);
        }

        if ($node instanceof Node\Stmt\ClassLike || $node instanceof Node\Stmt\Function_) {
            $docComment = $node->getDocComment();

            if (null !== $docComment && str_contains($docComment->getText(), '@internal')) {
                if (!$node->namespacedName || !\in_array($node->namespacedName->toString(), self::INTERNAL_CLASSES_FORCED, true)) {
                    return [];
                }
            }
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
     * @return null|int|Node|Node[]
     */
    public function leaveNode(Node $node, bool $preserveStack = false)
    {
        // Avoid duplicate use statements
        if ($node instanceof Node\Stmt\Use_) {
            $fullNamespace = $node->uses[0]->name->toString();

            if (\in_array($fullNamespace, $this->usesByNamespace[$this->currentNamespace] ?? [], true)) {
                return self::REMOVE_NODE;
            }

            $this->usesByNamespace[$this->currentNamespace][] = $fullNamespace;
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
}
