<?php

namespace Castor;

use Castor\Attribute\AsContext;

class ContextBuilder
{
    public function __construct(
        private AsContext $asContext,
        private \ReflectionFunction $function,
    ) {
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
