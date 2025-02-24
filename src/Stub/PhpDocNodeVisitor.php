<?php

namespace Castor\Stub;

use PhpParser\Node\Name;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\PhpVersion;
use PHPStan\PhpDoc\TypeNodeResolver;
use PHPStan\PhpDocParser\Ast\AbstractNodeVisitor;
use PHPStan\PhpDocParser\Ast\Node as PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** @internal */
#[Exclude]
class PhpDocNodeVisitor extends AbstractNodeVisitor
{
    private const SPECIAL_TYPES = [
        // Taken from https://github.com/phpstan/phpstan-src/blob/2.0.x/src/PhpDoc/TypeNodeResolver.php#L198
        'int',
        'integer',
        'positive-int',
        'negative-int',
        'non-positive-int',
        'non-negative-int',
        'non-zero-int',
        'string',
        'lowercase-string',
        'literal-string',
        'class-string',
        'interface-string',
        'trait-string',
        'enum-string',
        'callable-string',
        'array-key',
        'scalar',
        'empty-scalar',
        'non-empty-scalar',
        'number',
        'numeric',
        'numeric-string',
        'non-empty-string',
        'non-empty-lowercase-string',
        'truthy-string',
        'non-falsy-string',
        'non-empty-literal-string',
        'bool',
        'boolean',
        'true',
        'false',
        'null',
        'float',
        'double',
        'array',
        'associative-array',
        'non-empty-array',
        'iterable',
        'callable',
        'pure-callable',
        'pure-closure',
        'resource',
        'open-resource',
        'closed-resource',
        'mixed',
        'non-empty-mixed',
        'void',
        'object',
        'callable-object',
        'callable-array',
        'never',
        'noreturn',
        'never-return',
        'never-returns',
        'no-return',
        'list',
        'non-empty-list',
        '__always-list',
        'empty',
        '__stringandstringable',
        'self',
        'static',
        'parent',
        // More special types from https://github.com/phpstan/phpstan-src/blob/2.0.x/src/PhpDoc/TypeNodeResolver.php#L717
        'key-of',
        'value-of',
        'int-mask-of',
        'int-mask',
        '__benevolent',
        'template-type',
        'new',
    ];

    private readonly PhpVersion $phpVersion;

    public function __construct(
        private readonly NameResolver $nameResolver,
    ) {
        $this->phpVersion = PhpVersion::getHostVersion();
    }

    public function enterNode(PhpDocNode $node): ?PhpDocNode
    {
        // If the type is not fully qualified nor a builtin type, we want to resolve its FQCN
        if ($node instanceof IdentifierTypeNode
            && !str_starts_with($node->name, '\\')
            && !\in_array($node->name, self::SPECIAL_TYPES, true)
            && !$this->phpVersion->supportsBuiltinType($node->name)
        ) {
            $resolvedClassName = $this->nameResolver->getNameContext()->getResolvedClassName(new Name($node->name));

            // If the structure does not exist, keep the type as is
            // This is useful for special types like the ones used in @template phpdoc
            if (!$this->structureExists($resolvedClassName)) {
                return null;
            }

            return new IdentifierTypeNode(
                '\\' . ltrim(
                    $resolvedClassName,
                    '\\',
                )
            );
        }

        return null;
    }

    private function structureExists(string $name, bool $autoload = true): bool
    {
        return class_exists($name, $autoload)
            || interface_exists($name, $autoload)
            || trait_exists($name, $autoload);
    }
}
