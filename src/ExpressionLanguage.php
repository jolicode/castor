<?php

namespace Castor;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

/** @internal */
class ExpressionLanguage extends SymfonyExpressionLanguage
{
    public function __construct(
        private readonly ContextRegistry $contextRegistry,
    ) {
        parent::__construct();

        $this->addFunction(new ExpressionFunction(
            'var',
            fn () => throw new \LogicException('This function can only be used in expressions.'),
            fn ($vars, ...$args): mixed => $this->contextRegistry->getVariable(...$args),
        ));

        $this->addFunction(new ExpressionFunction(
            'context',
            fn () => throw new \LogicException('This function can only be used in expressions.'),
            fn ($vars, ...$args): Context => $this->contextRegistry->get(...$args),
        ));
    }
}
