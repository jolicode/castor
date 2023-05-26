<?php

namespace Castor;

use Castor\Attribute\AsContext;

/** @internal */
class ContextBuilder
{
    public function __construct(
        private readonly AsContext $asContext,
        private readonly \ReflectionFunction $function,
    ) {
    }

    /**
     * @return array<\ReflectionParameter>
     */
    public function getParameters(): array
    {
        return $this->function->getParameters();
    }

    public function build(...$args): Context
    {
        return $this->function->invoke(...$args);
    }

    public function isDefault(): bool
    {
        return $this->asContext->default;
    }

    public function getName(): string
    {
        return $this->asContext->name;
    }
}
