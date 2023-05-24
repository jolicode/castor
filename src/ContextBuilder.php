<?php

namespace Castor;

use Castor\Attribute\AsContext;

class ContextBuilder
{
    public function __construct(
        private readonly AsContext $asContext,
        private readonly \ReflectionFunction $function,
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            new AsContext('default', true),
            new \ReflectionFunction(fn () => new Context()),
        );
    }

    public function build(): Context
    {
        return $this->function->invoke();
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
