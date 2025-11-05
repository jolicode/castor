<?php

namespace Castor\PHPStan;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

if (class_exists(ProhibitCastorFunctionInClass::class)) {
    return [];
}

/**
 * @implements Rule<Node\Expr\FuncCall>
 */
class ProhibitCastorFunctionInClass implements Rule
{
    public function getNodeType(): string
    {
        return Node\Expr\FuncCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$scope->isInClass()) {
            return [];
        }

        $name = $node->name;

        if (!$name instanceof Node\Name) {
            return [];
        }

        $parts = $name->getParts();

        if (2 !== \count($parts)) {
            return [];
        }

        if ('Castor' !== $parts[0]) {
            return [];
        }

        if ('Internal' === $parts[1]) {
            return [];
        }

        return [
            RuleErrorBuilder::message(\sprintf(
                'Usage of Castor function "%s" is prohibited inside class.',
                $name->toString()
            ))
            ->identifier('prohibitCastorFunctionInClass')
            ->build(),
        ];
    }
}

return [];
