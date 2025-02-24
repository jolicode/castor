<?php

namespace Castor\Stub;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeVisitorAbstract;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class CleanVisitor extends NodeVisitorAbstract
{
    /** @var array<string, Namespace_> */
    public array $nodesByNamespace = [];

    /**
     * @return int|Node|null
     */
    public function enterNode(Node $node)
    {
        // Merge namespaces with the same name together
        if ($node instanceof Namespace_) {
            $currentNamespace = $node->name ? $node->name->toString() : null;

            if (!$currentNamespace) {
                return null;
            }

            $existingNode = $this->nodesByNamespace[$currentNamespace] ?? null;
            if ($existingNode) {
                $existingNode->stmts = array_merge($existingNode->stmts, $node->stmts);

                return self::REMOVE_NODE;
            }

            $this->nodesByNamespace[$currentNamespace] = $node;
        }

        return null;
    }

    /**
     * @return Node[]|null
     */
    public function afterTraverse(array $nodes)
    {
        $cleanedNodes = [];

        foreach ($nodes as $node) {
            // If namespace is empty, let's remove it
            if ($node instanceof Namespace_) {
                $stmts = array_filter($node->stmts, static fn (Node $stmt): bool => !$stmt instanceof Use_);

                if (!$stmts) {
                    continue;
                }

                $cleanedNodes[] = $node;
            }
        }

        return $cleanedNodes;
    }
}
