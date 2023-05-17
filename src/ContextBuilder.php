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
        $function = new \ReflectionFunction(fn () => new Context());

        return new self(
            new AsContext('default', true),
            $function,
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
